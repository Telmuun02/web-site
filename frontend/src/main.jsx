import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { GoogleOAuthProvider } from '@react-oauth/google'
// CSS-ийн ачаалах дараалал ЭНД шийдэгдэнэ:
//   index.css (токен/reset) → App.css (дундын хэв маяг) → компонентуудын CSS
// App.css-ийг App.jsx дотор import хийвэл компонентуудынхаас ХОЙНО ачаалагдаж,
// ижил жинтэй селекторууд дээр дундын дүрэм компонентынхыг дардаг байсан.
import './index.css'
import './App.css'
import App from './App.jsx'

// BrowserRouter — History API-г ашиглаж URL-ыг удирдана. Апп-ын хамгийн
// гадна талд ганц удаа тавина; доторх бүх Route / Link / useNavigate үүнээс
// контекстээ авдаг.
//
// GoogleOAuthProvider — Google-ийн script-ийг ачаалж, доторх бүх
// useGoogleLogin()-д client_id-г дамжуулна.
createRoot(document.getElementById('root')).render(
  <StrictMode>
    <GoogleOAuthProvider clientId={import.meta.env.VITE_GOOGLE_CLIENT_ID}>
      <BrowserRouter>
        <App />
      </BrowserRouter>
    </GoogleOAuthProvider>
  </StrictMode>,
)
