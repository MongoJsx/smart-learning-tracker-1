import { useEffect, useRef, useState } from 'react';
import { GoogleLogin } from '@react-oauth/google';

const Login = () => {
  const [ready, setReady] = useState(false);
  const submittingRef = useRef(false);

  useEffect(() => {
    setReady(true);
  }, []);

  const handleGoogleLoginSuccess = async (credentialResponse: any) => {
    if (submittingRef.current) return;
    submittingRef.current = true;

    try {
      const idToken = credentialResponse?.credential;
      if (!idToken) throw new Error('No credential');

      // ส่งไป backend ถ้าต้องการ (ปรับ URL/รูปแบบ payload ให้ตรงกับ backend)
      // const res = await fetch('/api/auth/google', {
      //   method: 'POST',
      //   headers: { 'Content-Type': 'application/json' },
      //   body: JSON.stringify({ id_token: idToken, credential: idToken }),
      // });
      // if (!res.ok) throw new Error(`Auth failed: ${res.status}`);
      // const data = await res.json();
      console.log('Google Login Success');
    } catch (e) {
      console.error(e);
    } finally {
      submittingRef.current = false;
    }
  };

  const handleGoogleLoginError = () => {
    console.log('Google Login Failed');
  };

  return (
    <div>
      {ready && (
        <GoogleLogin
          onSuccess={handleGoogleLoginSuccess}
          onError={handleGoogleLoginError}
        />
      )}
    </div>
  );
};

export default Login;
