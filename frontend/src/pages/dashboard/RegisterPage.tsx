import { useEffect, useState } from "react";
import { useAppAlert } from "../../context/AppAlertContext";

export default function RegisterPage() {
  const { success, error } = useAppAlert();
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
  });

  // โหลด Google Sign-In
  useEffect(() => {
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;
    if (!clientId) return;

    const script = document.createElement("script");
    script.src = "https://accounts.google.com/gsi/client";
    script.async = true;
    script.defer = true;
    document.body.appendChild(script);

    script.onload = () => {
      if (window.google) {
        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: handleGoogleRegister,
        });

        const googleBtn = document.getElementById("googleBtn");
        if (googleBtn) {
          window.google.accounts.id.renderButton(
            googleBtn,
            { theme: "outline", size: "large" }
          );
        }
      }
    };

    return () => {
      document.body.removeChild(script);
    };
  }, []);

  // สมัครด้วยฟอร์มปกติ
  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const payload = {
        ...form,
        password_confirmation: form.password,
      };
      const res = await fetch("/api/register", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(payload),
      }).then(async (resp) => {
        const data = await resp.json();
        if (!resp.ok) {
          throw data;
        }
        return data;
      });

      localStorage.setItem("token", res.token);
      success("สมัครสมาชิกสำเร็จ");
    } catch (err: any) {
      console.error(err);
      error("สมัครไม่สำเร็จ");
    }
  };

  // สมัครด้วย Google
  const handleGoogleRegister = async (response: any) => {
    try {
      const res = await fetch("/api/auth/google", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ credential: response.credential }),
      }).then(async (resp) => {
        const data = await resp.json();
        if (!resp.ok) {
          throw data;
        }
        return data;
      });

      localStorage.setItem("token", res.token);
      success("สมัคร/เข้าสู่ระบบด้วย Google สำเร็จ");
    } catch (err) {
      console.error(err);
      error("Google Login ไม่สำเร็จ");
    }
  };

  return (
    <div style={{ maxWidth: 400, margin: "auto" }}>
      <h2>สมัครสมาชิก</h2>

      <form onSubmit={handleRegister}>
        <input
          type="text"
          placeholder="ชื่อ"
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          required
        />
        <br />

        <input
          type="email"
          placeholder="อีเมล"
          value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          required
        />
        <br />

        <input
          type="password"
          placeholder="รหัสผ่าน"
          value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          required
        />
        <br />

        <button type="submit">สมัครสมาชิก</button>
      </form>

      <hr />

      <h3>หรือสมัครด้วย Google</h3>
      <div id="googleBtn"></div>
    </div>
  );
}
