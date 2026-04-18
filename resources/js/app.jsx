import './bootstrap';
import './i18n';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/auth/ProtectedRoute';
import LandingPage from './pages/LandingPage';
import PrivacyPage from './pages/PrivacyPage';
import TermsPage from './pages/TermsPage';
import LegalPage from './pages/LegalPage';
import SustainabilityPage from './pages/SustainabilityPage';
import RegisterPage from './pages/RegisterPage';
import LoginPage from './pages/LoginPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import ProfilePage from './pages/ProfilePage';
import DashboardPage from './pages/DashboardPage';
import ListDetailPage from './pages/ListDetailPage';
import SharedListPage from './pages/SharedListPage';
import WeeklySummaryPage from './pages/WeeklySummaryPage';
import AIGeneratePage from './pages/AIGeneratePage';
import HistoryPage from './pages/HistoryPage';

function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    {/* Public */}
                    <Route path="/" element={<LandingPage />} />
                    <Route path="/privacy" element={<PrivacyPage />} />
                    <Route path="/terms" element={<TermsPage />} />
                    <Route path="/legal" element={<LegalPage />} />
                    <Route path="/sustainability" element={<SustainabilityPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                    <Route path="/reset-password" element={<ResetPasswordPage />} />
                    <Route path="/shared/:tokenParam" element={<SharedListPage />} />

                    {/* Protected */}
                    <Route element={<ProtectedRoute />}>
                        <Route path="/app" element={<DashboardPage />} />
                        <Route path="/app/listas/:id" element={<ListDetailPage />} />
                        <Route path="/app/resumen" element={<WeeklySummaryPage />} />
                        <Route path="/app/generar" element={<AIGeneratePage />} />
                        <Route path="/app/historial" element={<HistoryPage />} />
                        <Route path="/app/profile" element={<ProfilePage />} />
                    </Route>
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}

const root = createRoot(document.getElementById('root'));
root.render(<App />);
