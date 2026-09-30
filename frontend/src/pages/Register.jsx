import { useState } from "react";
import { Link } from "react-router-dom";
import AuthLayout from "../components/AuthLayout";
import VerifyNotice from "../components/VerifyNotice";
import GoogleButton from "../components/GoogleButton";
import client from "../api/client";

// Бүртгүүлэх хуудас. handleSubmit нь backend-ийн /register endpoint-ыг дуудна.
// Анхаар: backend "confirmed" дүрэмтэй тул password_confirmation талбар шаардана.
//
// ХАТУУ ГОРИМ: backend бүртгэлийн хариунд token БУЦААХГҮЙ. Тиймээс энд шууд
// нэвтрүүлэхгүй — "мэйлээ шалгана уу" дэлгэц харуулж, хэрэглэгч и-мэйлээ
// баталгаажуулсны дараа /signin-ээр нэвтэрнэ.
// Харин Google-ээр бүртгүүлбэл Google и-мэйлийг баталгаажуулсан тул
// backend шууд token өгнө → onAuth(data)-аар нэвтрүүлнэ.
function Register({ onAuth }) {
  const [form, setForm] = useState({
    name: "",
    email: "",
    company_id: "",
    password: "",
    password_confirmation: "",
  });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  // Бүртгэл амжилттай болмогц энд и-мэйл хаяг орж, дэлгэц солигдоно.
  const [pendingEmail, setPendingEmail] = useState("");

  // Нэг handler-аар бүх талбарыг шинэчилнэ (input-ийн name-ээр).
  const update = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      // Амжилттай бол backend { user, email, message } буцаана (201). Token БАЙХГҮЙ.
      const { data } = await client.post("/register", form);
      setPendingEmail(data.email);
    } catch (err) {
      setError(err.response?.data?.message ?? "Бүртгэхэд алдаа гарлаа.");
    } finally {
      setLoading(false);
    }
  };

  // Бүртгэл амжилттай — форм биш, баталгаажуулах заавар харуулна.
  if (pendingEmail) {
    return (
      <AuthLayout
        title="Мэйлээ шалгана уу"
        subtitle="Бүртгэл үүслээ — дараагийн алхам ганцхан"
        footer={
          <>
            Баталгаажуулсан уу?{" "}
            <Link to="/signin" className="auth__link">
              Sign in
            </Link>
          </>
        }
      >
        <VerifyNotice email={pendingEmail} />
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title="Create account"
      subtitle="Join Folio and start borrowing"
      footer={
        <>
          Already have an account?{" "}
          <Link to="/signin" className="auth__link">
            Sign in
          </Link>
        </>
      }
    >
      <form className="auth-form" onSubmit={handleSubmit}>
        <label className="field">
          <span className="field__label">Full name</span>
          <input name="name" value={form.name} onChange={update} placeholder="Alex Chen" />
        </label>

        <label className="field">
          <span className="field__label">Email address</span>
          <input name="email" type="email" value={form.email} onChange={update} placeholder="you@example.com" />
        </label>

        <label className="field">
          <span className="field__label">Company ID</span>
          <input name="company_id" type="number" value={form.company_id} onChange={update} placeholder="3" />
        </label>

        <label className="field">
          <span className="field__label">Password</span>
          <input name="password" type="password" value={form.password} onChange={update} placeholder="••••••••" />
        </label>

        <label className="field">
          <span className="field__label">Confirm password</span>
          <input
            name="password_confirmation"
            type="password"
            value={form.password_confirmation}
            onChange={update}
            placeholder="••••••••"
          />
        </label>

        {error && <p style={{ color: "#c0392b", fontSize: "0.9rem" }}>{error}</p>}

        <button type="submit" className="btn-primary btn-block" disabled={loading}>
          {loading ? "Creating…" : "Create account"}
        </button>

        <div className="auth-divider">or</div>
        <GoogleButton onAuth={onAuth} onError={setError} />
      </form>
    </AuthLayout>
  );
}

export default Register;
