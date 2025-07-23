import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import axios from 'axios';
import { Toaster } from 'react-hot-toast';

// Import bootstrap after React components
import './bootstrap';

// Import components
import OnboardingPage from './components/OnboardingPage';
import LoginPage from './components/LoginPage';
import RegisterPage from './components/RegisterPage';
import MainLayout from './components/player/MainLayout';
import HostLayout from './components/host/HostLayout';

// Configure axios defaults
axios.defaults.baseURL = '/api';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

// Add axios interceptors
axios.interceptors.request.use(
    config => {
        const token = localStorage.getItem('userToken');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    error => {
        return Promise.reject(error);
    }
);

axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            localStorage.removeItem('userToken');
            localStorage.removeItem('userData');
            localStorage.removeItem('userType');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

// Main App Component
function App() {
    const navigate = useNavigate();
    const [userType, setUserType] = React.useState(localStorage.getItem('userType'));
    const [userToken, setUserToken] = React.useState(localStorage.getItem('userToken'));
    const [userData, setUserData] = React.useState(
        localStorage.getItem('userData') ? JSON.parse(localStorage.getItem('userData')) : null
    );

    const handleLoginSuccess = (type, token, data) => {
        console.log('Login success - User type:', type, 'Is host:', data.is_host);
        setUserType(type);
        setUserToken(token);
        setUserData(data);
        localStorage.setItem('userType', type);
        localStorage.setItem('userToken', token);
        localStorage.setItem('userData', JSON.stringify(data));
        navigate(type === 'host' ? '/host' : '/player');
    };

    const handleLogout = () => {
        localStorage.removeItem('userToken');
        localStorage.removeItem('userData');
        localStorage.removeItem('userType');
        setUserType(null);
        setUserToken(null);
        setUserData(null);
        navigate('/login');
    };

    return (
        <>
            <Toaster position="top-right" />
            <Routes>
                <Route
                    path="/"
                    element={
                        userToken ? (
                            <Navigate to={userType === 'host' ? '/host' : '/player'} />
                        ) : (
                            <Navigate to="/onboarding" />
                        )
                    }
                />
                <Route
                    path="/onboarding"
                    element={
                        userToken ? (
                            <Navigate to={userType === 'host' ? '/host' : '/player'} />
                        ) : (
                            <OnboardingPage />
                        )
                    }
                />
                <Route
                    path="/login"
                    element={
                        userToken ? (
                            <Navigate to={userType === 'host' ? '/host' : '/player'} />
                        ) : (
                            <LoginPage onLoginSuccess={handleLoginSuccess} />
                        )
                    }
                />
                <Route
                    path="/register"
                    element={
                        userToken ? (
                            <Navigate to={userType === 'host' ? '/host' : '/player'} />
                        ) : (
                            <RegisterPage onRegisterSuccess={handleLoginSuccess} />
                        )
                    }
                />
                <Route
                    path="/host/*"
                    element={
                        userToken && userType === 'host' ? (
                            <HostLayout
                                userToken={userToken}
                                userData={userData}
                                onLogout={handleLogout}
                            />
                        ) : (
                            <Navigate to="/login" />
                        )
                    }
                />
                <Route
                    path="/player/*"
                    element={
                        userToken && userType === 'player' ? (
                            <MainLayout
                                userToken={userToken}
                                userData={userData}
                                onLogout={handleLogout}
                            />
                        ) : (
                            <Navigate to="/login" />
                        )
                    }
                />
            </Routes>
        </>
    );
}

// Error Boundary Component
class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        console.error('Error caught by boundary:', error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen flex items-center justify-center bg-gray-50">
                    <div className="text-center">
                        <h1 className="text-2xl font-bold text-red-600 mb-4">Something went wrong</h1>
                        <button
                            onClick={() => window.location.reload()}
                            className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                        >
                            Refresh Page
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

// Wrap App with ErrorBoundary
const AppWithErrorBoundary = () => (
    <ErrorBoundary>
        <App />
    </ErrorBoundary>
);

// Mount the app
if (document.getElementById('app')) {
    const root = createRoot(document.getElementById('app'));
    root.render(
        <BrowserRouter>
            <AppWithErrorBoundary />
        </BrowserRouter>
    );
} 