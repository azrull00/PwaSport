import React from 'react';
import { createRoot } from 'react-dom/client';

// Import bootstrap after React components
import './bootstrap';

// Import components
import OnboardingPage from './components/OnboardingPage';
import LoginPage from './components/LoginPage';
import RegisterPage from './components/RegisterPage';

// Main App Component
function App() {
    const [currentPage, setCurrentPage] = React.useState('onboarding');
    const [userType, setUserType] = React.useState(null); // 'player' or 'host'

    // Handle page navigation
    const navigateTo = React.useCallback((page, type = null) => {
        setCurrentPage(page);
        if (type) setUserType(type);
    }, []);

    // Render current page based on state
    const renderCurrentPage = React.useCallback(() => {
        switch (currentPage) {
            case 'onboarding':
                return React.createElement(OnboardingPage, { onNavigate: navigateTo });
            case 'login':
                return React.createElement(LoginPage, { onNavigate: navigateTo, userType });
            case 'register':
                return React.createElement(RegisterPage, { onNavigate: navigateTo, userType });
            default:
                return React.createElement(OnboardingPage, { onNavigate: navigateTo });
        }
    }, [currentPage, userType, navigateTo]);

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