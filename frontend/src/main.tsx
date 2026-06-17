import ReactDOM from 'react-dom/client';
import { RouterProvider } from 'react-router-dom';
import './index.css';
import { router } from './routes';
import { AuthProvider } from './context/AuthContext';
import { AppAlertProvider } from './context/AppAlertContext';

ReactDOM.createRoot(document.getElementById('root') as HTMLElement).render(
  <AuthProvider>
    <AppAlertProvider>
      <RouterProvider
        router={router}
        future={{ v7_startTransition: true }} // Enable the future flag
      />
    </AppAlertProvider>
  </AuthProvider>
);
