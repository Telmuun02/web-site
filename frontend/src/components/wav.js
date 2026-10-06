// Хөтчийн бичлэгийг (webm/m4a) Chimege-ийн хүлээн авдаг WAV болгоно:
// 16 kHz, mono, 16-bit PCM. Сервер дээр ffmpeg хэрэггүй болно.
const SAMPLE_RATE = 16000;

export async function toWav16k(blob) {
  // 1. Шахсан аудиог (webm/m4a) түүхий дуу болгож задлана
  const ctx = new AudioContext();
  const decoded = await ctx.decodeAudioData(await blob.arrayBuffer());
  await ctx.close();

  // 2. 16 kHz, 1 суваг руу дахин түүвэрлэнэ — stereo бол хөтөч өөрөө нэгтгэнэ
  const offline = new OfflineAudioContext(1, Math.ceil(decoded.duration * SAMPLE_RATE), SAMPLE_RATE);
  const source = offline.createBufferSource();
  source.buffer = decoded;
  source.connect(offline.destination);
  source.start();
  const rendered = await offline.startRendering();

  // 3. WAV файл болгон бичнэ
  return new Blob([encodeWav(rendered.getChannelData(0))], { type: "audio/wav" });
}

// [-1..1] float → 44 byte толгой + 16-bit PCM
function encodeWav(samples) {
  const buffer = new ArrayBuffer(44 + samples.length * 2);
  const view = new DataView(buffer);
  const text = (offset, value) => [...value].forEach((c, i) => view.setUint8(offset + i, c.charCodeAt(0)));

  text(0, "RIFF");
  view.setUint32(4, 36 + samples.length * 2, true); // файлын нийт хэмжээ − 8
  text(8, "WAVE");
  text(12, "fmt ");
  view.setUint32(16, 16, true); //              fmt хэсгийн хэмжээ
  view.setUint16(20, 1, true); //               1 = PCM (шахаагүй)
  view.setUint16(22, 1, true); //               1 суваг (mono)
  view.setUint32(24, SAMPLE_RATE, true);
  view.setUint32(28, SAMPLE_RATE * 2, true); // секунд тутмын byte
  view.setUint16(32, 2, true); //               нэг түүврийн byte
  view.setUint16(34, 16, true); //              16-bit
  text(36, "data");
  view.setUint32(40, samples.length * 2, true);

  samples.forEach((s, i) => {
    const clamped = Math.max(-1, Math.min(1, s));
    view.setInt16(44 + i * 2, clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff, true);
  });

  return buffer;
}
