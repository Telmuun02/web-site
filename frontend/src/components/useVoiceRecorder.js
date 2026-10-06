import { useEffect, useRef, useState } from "react";

// Микрофоноос дуу бичих hook. start() → бичиж эхэлнэ, stop() → зогсоод
// onRecorded(blob)-ийг дуудна.
//
// Хөтөч бүр өөр формат бичдэг (Chrome/Edge/Safari — m4a, Firefox — webm).
// Аль нь ч байсан илгээхийн өмнө wav.js нь WAV 16 kHz болгоно (Chimege-д).
const FORMATS = ["audio/mp4;codecs=mp4a.40.2", "audio/mp4", "audio/webm;codecs=opus"];

// Нэг бичлэг 1 минутаас хэтрэхгүй — WAV ≈ 1.9 MB, backend 2 MB хүртэл хүлээж авна
const MAX_SECONDS = 60;
// Санамсаргүй товшилт — үүнээс богино бичлэгийг илгээхгүй
const MIN_MS = 700;

export const canRecord = () =>
  typeof window !== "undefined" &&
  !!navigator.mediaDevices?.getUserMedia &&
  typeof window.MediaRecorder !== "undefined";

// Олдохгүй бол "" — хөтөч өөрийн анхдагч форматаар бичнэ
const pickFormat = () => FORMATS.find((f) => MediaRecorder.isTypeSupported(f)) ?? "";

export function useVoiceRecorder({ onRecorded, onError }) {
  const [recording, setRecording] = useState(false);
  const [seconds, setSeconds] = useState(0);

  const recorderRef = useRef(null);
  const timerRef = useRef(null);

  // Микрофоны "бичиж байна" гэсэн заагч браузер дээр унтрахын тулд track-уудыг зогсооно
  const release = () => {
    clearInterval(timerRef.current);
    recorderRef.current?.stream.getTracks().forEach((t) => t.stop());
    recorderRef.current = null;
    setRecording(false);
  };

  // Чат хаагдах үед бичлэг үргэлжилсээр байвал микрофоныг суллана
  useEffect(() => release, []);

  const start = async () => {
    if (recorderRef.current) return;

    let stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (err) {
      onError(
        err.name === "NotAllowedError"
          ? "Микрофон ашиглах зөвшөөрөл өгөөгүй байна."
          : `Микрофон нээгдсэнгүй: ${err.message}`
      );
      return;
    }

    const mimeType = pickFormat();
    const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
    const chunks = [];
    const startedAt = Date.now();

    recorder.ondataavailable = (e) => {
      if (e.data.size > 0) chunks.push(e.data);
    };
    recorder.onstop = () => {
      const tooShort = Date.now() - startedAt < MIN_MS;
      release();
      if (tooShort || chunks.length === 0) return;
      onRecorded(new Blob(chunks, { type: recorder.mimeType || mimeType }));
    };

    recorderRef.current = recorder;
    recorder.start();
    setSeconds(0);
    setRecording(true);

    timerRef.current = setInterval(() => {
      const elapsed = Math.floor((Date.now() - startedAt) / 1000);
      setSeconds(elapsed);
      if (elapsed >= MAX_SECONDS) stop();
    }, 1000);
  };

  const stop = () => {
    if (recorderRef.current?.state === "recording") recorderRef.current.stop();
  };

  return { recording, seconds, start, stop };
}
