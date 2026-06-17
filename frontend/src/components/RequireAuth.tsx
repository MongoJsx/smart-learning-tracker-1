import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export const RequireAuth: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { user, loading, token } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center bg-transparent">
        <div className="h-12 w-12 animate-spin rounded-full border-4 border-primary/20 border-t-primary shadow-glow" />
      </div>
    );
  }

  if (!user || !token) {
    return <Navigate to="/auth/login" state={{ from: location }} replace />;
  }

// ✅ บังคับ remount ทุกหน้าตาม user id (ล้าง state เก่า ไม่เอาของคนอื่นมาโชว์)
return <div key={`user-${user.id}`}>{children}</div>;

};
