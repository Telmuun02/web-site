import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Sidebar from "../components/Sidebar";
import BookCard from "../components/BookCard";
import Pagination from "../components/Pagination";
import { SearchIcon } from "../components/icons";
import client from "../api/client";
import "./Catalog.css";

// API-ийн ном → UI-д хэрэгтэй энгийн бүтэц рүү хөрвүүлэх.
// BookResource: { authors: ["Нэр"], category: "Нэр", available: N }
function normalizeBook(book) {
  return {
    id: book.id,
    title: book.title,
    author: book.authors?.join(", ") || "Unknown",
    category: book.category ?? "Uncategorized",
    company: book.company ?? null,
    available: book.available,
    cover_url: book.cover_url,
  };
}

// Номын каталог. Одоо шүүлт/хайлт/pagination бүгд SERVER дээр хийгддэг —
// нэг хуудсанд ердөө 8 ном татна (бүх 108-ыг биш).
function Catalog({ query, onQueryChange, user }) {
  const [books, setBooks] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [category, setCategory] = useState("all");
  const [availability, setAvailability] = useState("all");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);

  // Ангиллыг нэг удаа татна (өөрчлөгддөггүй).
  useEffect(() => {
    client
      .get("/categories")
      .then((res) => setCategories(res.data.map((c) => c.name)))
      .catch(() => {});
  }, []);

  // Ном: хуудас/шүүлт/хайлт өөрчлөгдөх бүрд server-ээс тухайн хуудсыг татна.
  useEffect(() => {
    // Нэвтрээгүй бол хүсэлт огт явуулахгүй — /books нь одоо auth шаарддаг,
    // 401 авах нь дэмий. Доор нэвтрэх урилга харуулна.
    if (!user) {
      setBooks([]);
      setTotalCount(0);
      setLoading(false);
      return;
    }

    let active = true;
    setLoading(true);

    const q = query.trim();

    // ХАЙЛТЫН ҮГ БАЙВАЛ: embedding хайлт (/books/search) — LIKE биш, n-gram +
    // cosine. Backend оноогоор эрэмбэлсэн 10 номыг буцаадаг, хуудаслалт ба
    // ангилал/байдлын шүүлтийг мэдэхгүй. Тиймээс шүүлтийг ирсэн 10 ном дээр
    // энд хийж, хуудас нэг л гэж тооцно.
    //
    // ХАЙЛТЫН ҮГ БАЙХГҮЙ: хуучин зам — шүүлт/хуудаслалт SERVER дээр (/books).
    const request = q
      ? client.get("/books/search", { params: { q } }).then((res) => {
          let list = res.data.data.map(normalizeBook);
          if (category !== "all") list = list.filter((b) => b.category === category);
          if (availability === "available") list = list.filter((b) => b.available > 0);
          else if (availability === "checked-out") list = list.filter((b) => b.available === 0);
          return { books: list, lastPage: 1, total: list.length };
        })
      : client
          .get("/books", {
            params: {
              page,
              ...(category !== "all" && { category }),
              ...(availability !== "all" && { availability }),
            },
          })
          .then((res) => ({
            books: res.data.data.map(normalizeBook), // тухайн хуудсын 8 ном
            lastPage: res.data.meta.last_page, // нийт хэдэн хуудас
            total: res.data.meta.total, // нийт хэдэн ном (шүүлтийн дараа)
          }));

    request
      .then(({ books, lastPage, total }) => {
        if (!active) return;
        setBooks(books);
        setTotalPages(lastPage);
        setTotalCount(total);
        setError("");
      })
      .catch((err) => {
        if (!active) return;
        setError(
          err.response?.status === 401
            ? "Нэвтрэх хугацаа дууссан байна. Дахин нэвтэрнэ үү."
            : "Өгөгдөл ачаалж чадсангүй. Backend асаалттай юу?"
        );
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [page, category, availability, query, user]);

  // Шүүлт/хайлт өөрчлөгдвөл эхний хуудас руу буцаана.
  useEffect(() => {
    setPage(1);
  }, [category, availability, query]);

  // Ном зээлэх: /loans руу POST, амжилттай бол локал available-ийг 1 бууруулна.
  const handleBorrow = async (bookId) => {
    const due = new Date();
    due.setDate(due.getDate() + 14);
    const dueDate = due.toISOString().slice(0, 10);

    try {
      await client.post("/loans", { book_id: bookId, due_date: dueDate });
      setBooks((prev) =>
        prev.map((b) => (b.id === bookId ? { ...b, available: b.available - 1 } : b))
      );
    } catch (err) {
      alert(err.response?.data?.message ?? "Зээлэхэд алдаа гарлаа.");
    }
  };

  // Нэвтрээгүй үед: каталогийн оронд нэвтрэх урилга.
  // Ном бүр компанид харьяалагддаг тул хэн болохыг мэдэхгүйгээр юу
  // харуулахыг тодорхойлох боломжгүй.
  if (!user) {
    return (
      <div className="page">
        <h1 className="page__title">Library Catalog</h1>
        <p className="empty-state">
          Номын жагсаалтыг үзэхийн тулд нэвтэрнэ үү. Та зөвхөн өөрийн
          байгууллагад харьяалагдах номыг харах боломжтой.
        </p>
        <div className="detail__actions">
          <Link to="/signin" className="btn-primary">
            Нэвтрэх
          </Link>
          <Link to="/register" className="btn-text">
            Бүртгүүлэх
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="layout">
      <Sidebar
        categories={categories}
        category={category}
        onCategory={setCategory}
        availability={availability}
        onAvailability={setAvailability}
      />

      <main className="content">
        <div className="content__head">
          <h1>Library Catalog</h1>
          <p className="content__count">
            {totalCount} books found
            {/* Админ бүх компанийн номыг харна — тэр ялгааг тодорхой хэлнэ */}
            {user.role === "admin"
              ? " · бүх байгууллага"
              : user.company?.name
                ? ` · ${user.company.name}`
                : ""}
          </p>
        </div>

        <div className="search-box content__search">
          <SearchIcon />
          <input
            type="search"
            placeholder="Search by title or author…"
            value={query}
            onChange={(e) => onQueryChange(e.target.value)}
          />
        </div>

        {loading ? (
          <p className="empty-state">Loading…</p>
        ) : error ? (
          <p className="empty-state">{error}</p>
        ) : books.length === 0 ? (
          <p className="empty-state">No books match your filters.</p>
        ) : (
          <>
            <div className="grid">
              {books.map((book) => (
                <BookCard key={book.id} book={book} user={user} onBorrow={handleBorrow} />
              ))}
            </div>

            <Pagination page={page} totalPages={totalPages} onPage={setPage} />
          </>
        )}
      </main>
    </div>
  );
}

export default Catalog;
