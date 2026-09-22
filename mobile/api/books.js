/**
 * ===== Номтой холбоотой хэлбэр + туслах функцууд =====
 *
 * Веб дээр `normalizeBook` нь Catalog.jsx болон BookDetail.jsx ХОЁУЛАНД
 * тус тусад нь бичигдсэн байсан. Зээлэх огноо тооцоолох 3 мөр ч мөн адил.
 * Утсан дээр эхнээс нь нэг газар төвлөрүүлье — API-ийн хариу өөрчлөгдвөл
 * НЭГ файл засахад хангалттай.
 *
 * ЭНЭ ФАЙЛ .js — тип нь JSDoc тайлбар дотор амьдарна (client.js-тэй ижил).
 */

import client from './client';

/**
 * Жагсаалтад харагдах ном (BookResource).
 *
 * @typedef {object} Book
 * @property {number} id
 * @property {string} title
 * @property {string} author
 * @property {string} category
 * @property {string | null} company
 * @property {number} available
 * @property {string | null} cover_url
 */

/**
 * Дэлгэрэнгүй хуудасны ном (BookDetailResource) — дээрхээс илүү талбартай.
 *
 * `&` нь TS-ийн intersection-тай яг ижил утгатай: Book-ийн бүх талбар дээр
 * нэмээд эдгээр гурав.
 *
 * @typedef {Book & {
 *   isbn: string | null,
 *   authors: string[],
 *   total: number | null,
 * }} BookDetail
 */

/**
 * API-ийн ном → UI-д хэрэгтэй энгийн бүтэц.
 * BookResource: { authors: ["Нэр"], category: "Нэр", available: N }
 *
 * @param {any} raw
 * @returns {Book}
 */
export function normalizeBook(raw) {
  return {
    id: raw.id,
    title: raw.title,
    author: raw.authors?.join(', ') || 'Unknown',
    category: raw.category ?? 'Uncategorized',
    company: raw.company ?? null,
    available: raw.available,
    cover_url: raw.cover_url ?? null,
  };
}

/**
 * Дэлгэрэнгүй хариу.
 *
 * `??`-ууд нь хуучин түүхий загварын хэлбэрийг ч дэмжсэн хэвээр — кэш эсвэл
 * хуучин хариу ирвэл ч эвдрэхгүй (веб хувилбартай ижил бодлого).
 *
 * @param {any} raw
 * @returns {BookDetail}
 */
export function normalizeBookDetail(raw) {
  /** @type {string[]} */
  const authors = (raw.authors ?? []).map((/** @type {any} */ a) => a?.name ?? a);

  return {
    id: raw.id,
    title: raw.title,
    isbn: raw.isbn ?? null,
    category: raw.category?.name ?? raw.category ?? 'Uncategorized',
    company: raw.company?.name ?? raw.company ?? null,
    authors,
    author: authors.join(', ') || 'Unknown',
    available: raw.available ?? raw.available_copies ?? 0,
    total: raw.total ?? raw.total_copies ?? null,
    cover_url: raw.cover_url ?? null,
  };
}

/**
 * Утгын хайлт — GET /books/search?q=...
 *
 * /books?search= нь SQL LIKE (яг үг таарах) бол энэ нь backend-ийн embedding
 * (n-gram + cosine) хайлт: нөхцөл, бичгийн алдаанд тэсвэртэй, оноогоор
 * эрэмбэлсэн. Хуудаслалт байхгүй — хамгийн ойрхон 10 номыг шууд буцаана.
 *
 * @param {string} q
 * @returns {Promise<Book[]>}
 */
export async function searchBooks(q) {
  const res = await client.get('/books/search', { params: { q } });
  return res.data.data.map(normalizeBook);
}

/**
 * Зээлийн хугацаа: өнөөдрөөс хойш 14 хоног, "YYYY-MM-DD" хэлбэрээр.
 *
 * @returns {string}
 */
export function dueDateIn14Days() {
  const due = new Date();
  due.setDate(due.getDate() + 14);

  // toISOString() нь UTC руу хөрвүүлдэг тул шөнө дунд орчимд нэг өдрөөр зөрж
  // болзошгүй. Тиймээс орон нутгийн цагаар гараар угсарна (Cart.jsx-тэй ижил).
  const m = String(due.getMonth() + 1).padStart(2, '0');
  const d = String(due.getDate()).padStart(2, '0');
  return `${due.getFullYear()}-${m}-${d}`;
}

/**
 * Ном зээлэх. Амжилттай бол буцаах огноог буцаана.
 *
 * АНХААР: backend нь энэ хүсэлтийг queue руу оруулаад 202 Accepted буцаадаг
 * (CreateLoanJob). Өөрөөр хэлбэл "хүлээж авлаа" гэсэн үг болохоос "хийгдлээ"
 * гэсэн үг биш — тиймээс зээлсэн ном "Миний зээл" дээр шууд гарч ирэхгүй.
 *
 * @param {number} bookId
 * @returns {Promise<string>}
 */
export async function borrowBook(bookId) {
  const dueDate = dueDateIn14Days();
  await client.post('/loans', { book_id: bookId, due_date: dueDate });
  return dueDate;
}
