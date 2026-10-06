import { useEffect, useRef, useState } from "react";
import ChatBubbleOutlineIcon from "@mui/icons-material/ChatBubbleOutlined";
import CloseIcon from "@mui/icons-material/Close";
import SendIcon from "@mui/icons-material/Send";
import MicIcon from "@mui/icons-material/Mic";
import StopIcon from "@mui/icons-material/Stop";
import client from "../api/client";
import { canRecord, useVoiceRecorder } from "./useVoiceRecorder";
import { toWav16k } from "./wav";
import "./ChatWidget.css";

// Дэлгэцийн баруун доод буланд байрлах чат. Хаалттай үед зөвхөн дугуй товч,
// дарахад чатны цонх нээгдэнэ. Header шиг бүх хуудсанд харагдах тул
// App.jsx дотор <Routes>-ийн гадна render хийгдэнэ.

// POST /api/chat { message } → { reply }. Backend-тэй харьцах ганц цэг —
// API өөрчлөгдвөл зөвхөн энд засна. client нь baseURL + Bearer token-ыг
// өөрөө наана (api/client.js).
// Зочны чатны id — нэг удаа үүсээд localStorage-д хадгалагдана, refresh хийсэн
// ч хэвээр. Нэвтэрсэн хэрэглэгчийг backend token-оор нь таних тул энэ нь
// зөвхөн зочинд хэрэгтэй, гэхдээ үргэлж илгээхэд гэм байхгүй.
function getChatId() {
  let id = localStorage.getItem("chat_id");
  if (!id) {
    id = crypto.randomUUID();
    localStorage.setItem("chat_id", id);
  }
  return id;
}

async function sendMessage(text) {
  const { data } = await client.post("/chat", { message: text, chat_id: getChatId() });
  // reply — жинхэнэ хариу. message — controller-ийн туршилтын хувилбар
  // ('Chat API is working.'); гинж ажиллаж байгааг шалгахад хэрэгтэй.
  return data.reply ?? data.message ?? JSON.stringify(data);
}

// POST /api/chat/transcribe (multipart) { audio: voice.wav } → { text }.
// Backend дууг Chimege руу илгээж текст болгоно. Чат руу илгээхгүй —
// текст input-д орж, хэрэглэгч шалгаад ердийн мессеж шиг илгээнэ.
async function transcribe(blob) {
  const wav = await toWav16k(blob);
  const form = new FormData();
  form.append("audio", new File([wav], "voice.wav", { type: "audio/wav" }));

  // Content-Type-ийг axios өөрөө multipart/form-data (boundary-тай) болгоно
  const { data } = await client.post("/chat/transcribe", form);
  return data.text ?? "";
}

// Backend-ийн хариулсан статус/мессежийг харуулна (404 — route алга,
// 405 — method буруу, 422 — validation, 500 — controller-ийн алдаа…).
function errorText(err) {
  const status = err.response?.status;
  const detail = err.response?.data?.message ?? err.message;
  return status ? `Алдаа ${status}: ${detail}` : `Алдаа: ${detail}`;
}

// 65 → "1:05"
const formatTime = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;

// GET /api/chat/history → { messages: [{ role: "user" | "assistant", content }] }.
// Backend-ийн "assistant"-ийг widget-ийн "bot" болгож хөрвүүлнэ.
async function loadHistory() {
  const { data } = await client.get("/chat/history", { params: { chat_id: getChatId() } });
  return (data.messages ?? []).map((m, i) => ({
    id: `h${i}`,
    role: m.role === "assistant" ? "bot" : "user",
    text: m.content,
  }));
}

function ChatWidget() {
  // Цонх нээлттэй эсэх
  const [open, setOpen] = useState(false);
  // { id, role: "user" | "bot", text } — role нь CSS-д аль талд харуулахыг шийднэ
  const [messages, setMessages] = useState([
    { id: 0, role: "bot", text: "Сайн байна уу! Би Folio-ийн туслах. Юугаар туслах вэ?" },
  ]);
  // Controlled input
  const [input, setInput] = useState("");
  // Хариу хүлээж байх үед давхар илгээхээс сэргийлнэ
  const [sending, setSending] = useState(false);
  // Дууг текст болгож байх үед (Chimege 2–5 сек)
  const [transcribing, setTranscribing] = useState(false);
  // Текст ормогц курсорыг input руу буцаана — шууд засаж эсвэл Enter дарж болно
  const inputRef = useRef(null);

  // Анх ачаалахад (refresh-ийн дараа ч) өмнөх чатыг backend-ээс татна.
  // Мэндчилгээг эхэнд нь үлдээгээд түүхийг ард нь залгана.
  useEffect(() => {
    loadHistory()
      .then((history) => {
        if (history.length) setMessages((prev) => [prev[0], ...history]);
      })
      .catch(() => {}); // Түүх ачаалагдахгүй бол хоосон чатаар үргэлжилнэ
  }, []);

  // Шинэ мессеж ирэх бүрт жагсаалтын хамгийн доош гүйлгэнэ.
  // Хоосон <div>-ийг жагсаалтын төгсгөлд тавиад түүн рүү scrollIntoView хийнэ.
  const bottomRef = useRef(null);
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, open]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const text = input.trim();
    if (!text || sending) return;

    // Хэрэглэгчийн мессежийг шууд харуулна (хариу хүлээхгүй)
    setMessages((prev) => [...prev, { id: Date.now(), role: "user", text }]);
    setInput("");
    setSending(true);

    try {
      const reply = await sendMessage(text);
      setMessages((prev) => [...prev, { id: Date.now() + 1, role: "bot", text: reply }]);
    } catch (err) {
      addBotMessage(errorText(err));
    } finally {
      setSending(false);
    }
  };

  const addBotMessage = (text) =>
    setMessages((prev) => [...prev, { id: Date.now() + 1, role: "bot", text }]);

  // Бичлэг дуусмагц: текст болгоод input-д нэмнэ (чат руу илгээхгүй).
  // Хэрэглэгч уншаад, хэрэгтэй бол засаад, илгээх товчоор ердийн мессеж шиг илгээнэ.
  const handleVoice = async (blob) => {
    setTranscribing(true);

    try {
      const text = await transcribe(blob);
      // Өмнө нь бичсэн текст байвал араас нь залгана
      setInput((prev) => (prev.trim() ? `${prev.trim()} ${text}` : text));
      setTimeout(() => inputRef.current?.focus());
    } catch (err) {
      addBotMessage(errorText(err));
    } finally {
      setTranscribing(false);
    }
  };

  const voice = useVoiceRecorder({ onRecorded: handleVoice, onError: addBotMessage });

  return (
    <div className="chat">
      {/* Цонх — зөвхөн open үед render хийгдэнэ */}
      {open && (
        <div className="chat__panel" role="dialog" aria-label="Чат">
          <div className="chat__header">
            <span className="chat__title">Folio туслах</span>
            <button
              type="button"
              className="chat__close"
              onClick={() => setOpen(false)}
              aria-label="Хаах"
            >
              <CloseIcon fontSize="small" />
            </button>
          </div>

          <div className="chat__messages">
            {messages.map((m) => (
              <div key={m.id} className={`chat__msg chat__msg--${m.role}`}>
                {m.text}
              </div>
            ))}
            {sending && <div className="chat__msg chat__msg--bot chat__msg--typing">…</div>}
            <div ref={bottomRef} />
          </div>

          {/* <form> — Enter дарахад submit болно, тусад нь keydown барих хэрэггүй */}
          <form className="chat__form" onSubmit={handleSubmit}>
            <input
              ref={inputRef}
              type="text"
              placeholder={
                voice.recording
                  ? `Бичиж байна… ${formatTime(voice.seconds)}`
                  : transcribing
                    ? "Текст болгож байна…"
                    : "Мессеж бичих…"
              }
              value={input}
              onChange={(e) => setInput(e.target.value)}
              disabled={voice.recording || transcribing}
              autoFocus
            />
            {/* Дарахад бичиж эхэлнэ, дахин дарахад зогсоод текст болгоно.
                Микрофон дэмждэггүй браузерт (эсвэл http дээр) товч харагдахгүй. */}
            {canRecord() && (
              <button
                type="button"
                className={`chat__mic${voice.recording ? " chat__mic--recording" : ""}`}
                onClick={voice.recording ? voice.stop : voice.start}
                disabled={sending || transcribing}
                aria-label={voice.recording ? "Бичлэг зогсоох" : "Дуугаар бичих"}
              >
                {voice.recording ? <StopIcon fontSize="small" /> : <MicIcon fontSize="small" />}
              </button>
            )}
            <button
              type="submit"
              className="chat__send"
              disabled={!input.trim() || sending || voice.recording || transcribing}
              aria-label="Илгээх"
            >
              <SendIcon fontSize="small" />
            </button>
          </form>
        </div>
      )}

      {/* Дугуй товч — нээлттэй үед хаах, хаалттай үед нээх */}
      <button
        type="button"
        className="chat__toggle"
        onClick={() => setOpen((o) => !o)}
        aria-label={open ? "Чат хаах" : "Чат нээх"}
        aria-expanded={open}
      >
        {open ? <CloseIcon /> : <ChatBubbleOutlineIcon />}
      </button>
    </div>
  );
}

export default ChatWidget;
