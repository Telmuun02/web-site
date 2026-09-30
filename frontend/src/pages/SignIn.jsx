import { useState } from "react";
import { Link } from "react-router-dom";
import AuthLayout from "../components/AuthLayout";
import VerifyNotice from "../components/VerifyNotice";
import GoogleButton from "../components/GoogleButton";
import client from "../api/client";

// Нэвтрэх хуудас (/signin). handleSubmit нь backend-ийн /login endpoint-ыг дуудна.
// Амжилттай бол onAuth(data) — App нь token хадгалж, "/" руу шилжүүлнэ.
function SignIn({ onAuth }) {
  const [form, setForm] = useState({ email: "", password: "" });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  // Нууц үг зөв ч и-мэйл баталгаажаагүй үед backend 403 + email_unverified буцаана.
  const [unverifiedEmail, setUnverifiedEmail] = useState("");

  const update = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setUnverifiedEmail("");
    setLoading(true);
    try {
      // Амжилттай бол backend { user, token } буцаана.
      const { data } = await client.post("/login", form);
      onAuth(data); // App-д token хадгалж, каталог руу шилжинэ.
    } catch (err) {
      // 403 + email_unverified — нууц үг зөв, зөвхөн баталгаажуулалт дутуу.
      // Энэ тохиолдолд улаан алдаа биш, дахин илгээх самбар харуулна.
      if (err.response?.status === 403 && err.response?.data?.email_unverified) {
        setUnverifiedEmail(err.response.data.email);
      } else {
        // Laravel алдааны мессежийг харуулна (422 = буруу нэвтрэлт).
        setError(err.response?.data?.message ?? "Нэвтрэхэд алдаа гарлаа.");
      }
    } finally {
      setLoading(false);
    }
  };

  // Баталгаажаагүй — формын оронд заавар + дахин илгээх товч.
  if (unverifiedEmail) {
    return (
      <AuthLayout
        title="И-мэйл баталгаажаагүй"
        subtitle="Нэвтрэхийн өмнө хаягаа баталгаажуулна уу"
        footer={
          <>
            Баталгаажуулсан уу?{" "}
            <button type="button" className="auth__link" onClick={() => setUnverifiedEmail("")}>
              Дахин нэвтрэх
            </button>
          </>
        }
      >
        <VerifyNotice email={unverifiedEmail} />
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title="Welcome back"
      subtitle="Sign in to your Folio account"
      footer={
        <>
          Don&apos;t have an account?{" "}
          <Link to="/register" className="auth__link">
            Register
          </Link>
        </>
      }
    >
      <form className="auth-form" onSubmit={handleSubmit}>
        <label className="field">
          <span className="field__label">Email address</span>
          <input name="email" type="email" value={form.email} onChange={update} placeholder="you@example.com" />
        </label>

        <label className="field">
          <span className="field__label">Password</span>
          <input name="password" type="password" value={form.password} onChange={update} placeholder="••••••••" />
        </label>

        {/* Алдаа гарвал улаанаар харуулна */}
        {error && <p style={{ color: "#c0392b", fontSize: "0.9rem" }}>{error}</p>}

        <button type="submit" className="btn-primary btn-block" disabled={loading}>
          {loading ? "Signing in…" : "Sign in"}
        </button>

        <div className="auth-divider">or</div>
        <GoogleButton onAuth={onAuth} onError={setError} />

        {/* Seed-ээр үүсгэсэн туршилтын бүртгэлүүд */}
        <div className="auth-demo">
          <p>Demo accounts (нууц үг: <code>password</code>):</p>
          <p><code>admin@example.com</code> → Admin</p>
          <p><code>test@example.com</code> → Member</p>
        </div>
      </form>
    </AuthLayout>
  );
}

export default SignIn;
