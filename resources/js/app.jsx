import React from 'react';
import { createRoot } from 'react-dom/client';

// Import bootstrap after React components
import './bootstrap';

// Import components
import OnboardingPage from './components/OnboardingPage';
import LoginPage from './components/LoginPage';
import RegisterPage from './components/RegisterPage';
import MainLayout from './components/player/MainLayout';

// Main App Component
function App() {
    const [currentPage, setCurrentPage] = React.useState('onboarding');
    const [userType, setUserType] = React.useState(null); // 'player' or 'host'
    const [userToken, setUserToken] = React.useState(null);
    const [userData, setUserData] = React.useState(null);
    const [isAuthenticated, setIsAuthenticated] = React.useState(false);

    // Handle page navigation
    const navigateTo = React.useCallback((page, type = null) => {
        setCurrentPage(page);
        if (type) setUserType(type);
    }, []);

    // Handle successful login
    const handleLoginSuccess = React.useCallback((token, user, type) => {
        setUserToken(token);
        setUserData(user);
        setUserType(type);
        setIsAuthenticated(true);
        setCurrentPage('dashboard');
        
        // Store in localStorage for persistence
        localStorage.setItem('userToken', token);
        localStorage.setItem('userData', JSON.stringify(user));
        localStorage.setItem('userType', type);
    }, []);

    // Handle logout
    const handleLogout = React.useCallback(async () => {
        try {
            if (userToken) {
                await fetch('/api/auth/logout', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${userToken}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            // Clear state and localStorage
            setUserToken(null);
            setUserData(null);
            setUserType(null);
            setIsAuthenticated(false);
            setCurrentPage('onboarding');
            
            localStorage.removeItem('userToken');
            localStorage.removeItem('userData');
            localStorage.removeItem('userType');
        }
    }, [userToken]);

    // Check for existing authentication on page load
    React.useEffect(() => {
        const storedToken = localStorage.getItem('userToken');
        const storedUserData = localStorage.getItem('userData');
        const storedUserType = localStorage.getItem('userType');

        if (storedToken && storedUserData && storedUserType) {
            try {
                const parsedUserData = JSON.parse(storedUserData);
                setUserToken(storedToken);
                setUserData(parsedUserData);
                setUserType(storedUserType);
                setIsAuthenticated(true);
                setCurrentPage('dashboard');
            } catch (error) {
                console.error('Error parsing stored user data:', error);
                // Clear corrupted data
                localStorage.removeItem('userToken');
                localStorage.removeItem('userData');
                localStorage.removeItem('userType');
            }
        }
    }, []);

    // Render current page based on state
    const renderCurrentPage = React.useCallback(() => {
        // If authenticated, show dashboard
        if (isAuthenticated && currentPage === 'dashboard') {
            if (userType === 'player') {
                return React.createElement(MainLayout, { 
                    userType, 
                    userToken, 
                    userData, 
                    onLogout: handleLogout 
                });
            } else {
                // TODO: Host dashboard
                return React.createElement('div', { className: 'p-6' }, [
                    React.createElement('h1', { key: 'title', className: 'text-2xl font-bold' }, 'Host Dashboard'),
                    React.createElement('p', { key: 'message' }, 'Coming soon...'),
                    React.createElement('button', { 
                        key: 'logout',
                        onClick: handleLogout,
                        className: 'mt-4 bg-red-500 text-white px-4 py-2 rounded'
                    }, 'Logout')
                ]);
            }
        }

        switch (currentPage) {
            case 'onboarding':
                return React.createElement(OnboardingPage, { onNavigate: navigateTo });
            case 'login':
                return React.createElement(LoginPage, { 
                    onNavigate: navigateTo, 
                    userType,
                    onLoginSuccess: handleLoginSuccess
                });
            case 'register':
                return React.createElement(RegisterPage, { 
                    onNavigate: navigateTo, 
                    userType,
                    onLoginSuccess: handleLoginSuccess
                });
            default:
                return React.createElement(OnboardingPage, { onNavigate: navigateTo });
        }
    }, [currentPage, userType, isAuthenticated, userToken, userData, navigateTo, handleLoginSuccess, handleLogout]);

    return React.createElement('div', { 
        className: 'min-h-screen bg-secondary' 
    }, renderCurrentPage());
}

// Initialize React app with error boundary
function AppWithErrorBoundary() {
    const [hasError, setHasError] = React.useState(false);

    React.useEffect(() => {
        const handleError = (error) => {
            console.error('React Error:', error);
            setHasError(true);
        };

        window.addEventListener('error', handleError);
        return () => window.removeEventListener('error', handleError);
    }, []);

    if (hasError) {
        return React.createElement('div', { 
            style: { padding: '20px', textAlign: 'center' } 
        }, [
            React.createElement('h1', { key: 'title' }, 'Something went wrong'),
            React.createElement('p', { key: 'message' }, 'Please refresh the page')
        ]);
    }

    return React.createElement(App);
}

// Initialize React app
const container = document.getElementById('app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(AppWithErrorBoundary));
} else {
    console.error('Could not find app container element');
} 