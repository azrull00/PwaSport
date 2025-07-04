import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { 
    HiHome, 
    HiUserGroup, 
    HiCalendar, 
    HiLocationMarker, 
    HiChartBar,
    HiCog,
    HiUsers,
    HiClipboardList
} from 'react-icons/hi';
import HostDashboard from './HostDashboard';
import PlayerManagement from './PlayerManagement';
import GuestPlayerManagement from './GuestPlayerManagement';
import MatchmakingDashboard from './MatchmakingDashboard';
import CourtManagement from './CourtManagement';
import VenueManagement from './VenueManagement';
import CommunityManagement from './CommunityManagement';

const HostLayout = ({ userToken, userData, onLogout }) => {
    const [user, setUser] = useState(userData);
    const [activeTab, setActiveTab] = useState('dashboard');
    const [currentView, setCurrentView] = useState({ page: 'dashboard', params: null });
    const [hasCommunity, setHasCommunity] = useState(false);
    const location = useLocation();

    const navigationTabs = [
        { id: 'dashboard', icon: HiHome, label: 'Dashboard' },
        { id: 'players', icon: HiUsers, label: 'Players' },
        { id: 'events', icon: HiCalendar, label: 'Events' },
        { id: 'matchmaking', icon: HiChartBar, label: 'Matchmaking' },
        { id: 'venues', icon: HiLocationMarker, label: 'Venues' },
        { id: 'community', icon: HiUserGroup, label: 'Community' }
    ];

    // Check if host has any communities
    useEffect(() => {
        const checkCommunities = async () => {
            try {
                const response = await fetch('/api/communities/my-communities', {
                    headers: {
                        'Authorization': `Bearer ${userToken}`,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                const hasExistingCommunity = data.data.communities.length > 0;
                setHasCommunity(hasExistingCommunity);
                
                // If no community exists, redirect to community creation
                if (!hasExistingCommunity) {
                    setActiveTab('community');
                    setCurrentView({ page: 'community', params: { showCreate: true } });
                }
            } catch (error) {
                console.error('Error checking communities:', error);
            }
        };

        checkCommunities();
    }, [userToken]);

    // Fetch updated user profile
    const fetchUserProfile = async () => {
        try {
            const response = await fetch('/api/auth/profile', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.status === 'success') {
                    setUser(data.data.user);
                }
            }
        } catch (error) {
            console.error('Error fetching user profile:', error);
        }
    };

    useEffect(() => {
        if (userToken && !user) {
            fetchUserProfile();
        }
    }, [userToken]);

    const handleNavigation = (page, params = null) => {
        setActiveTab(page);
        setCurrentView({ page, params });
    };

    const isActive = (path) => {
        return location.pathname === path;
    };

    const renderContent = () => {
        switch (currentView.page) {
            case 'dashboard':
                return <HostDashboard user={user} userToken={userToken} onNavigate={handleNavigation} />;
            case 'players':
                return currentView.params?.showGuests ? (
                    <GuestPlayerManagement 
                        eventId={currentView.params?.eventId} 
                        userToken={userToken} 
                        onNavigate={handleNavigation}
                    />
                ) : (
                    <PlayerManagement 
                        eventId={currentView.params?.eventId} 
                        userToken={userToken} 
                        onNavigate={handleNavigation}
                    />
                );
            case 'events':
                return <MatchmakingDashboard userToken={userToken} onNavigate={handleNavigation} />;
            case 'matchmaking':
                return (
                    <CourtManagement 
                        eventId={currentView.params?.eventId}
                        userToken={userToken}
                        onNavigate={handleNavigation}
                    />
                );
            case 'venues':
                return <VenueManagement userToken={userToken} onNavigate={handleNavigation} />;
            case 'community':
                return (
                    <CommunityManagement 
                        userToken={userToken} 
                        onNavigate={handleNavigation}
                        showCreate={currentView.params?.showCreate}
                    />
                );
            default:
                return <HostDashboard user={user} userToken={userToken} onNavigate={handleNavigation} />;
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Top Navigation Bar */}
            <nav className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <img
                                    className="h-8 w-auto"
                                    src="/logo.png"
                                    alt="SportPWA"
                                />
                            </div>
                            <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
                                {navigationTabs.map(tab => (
                                    <button
                                        key={tab.id}
                                        onClick={() => handleNavigation(tab.id)}
                                        className={`${
                                            activeTab === tab.id
                                                ? 'border-blue-500 text-gray-900'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                                    >
                                        <tab.icon className="w-5 h-5 mr-2" />
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="flex items-center">
                            <button
                                onClick={onLogout}
                                className="ml-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Mobile Navigation */}
            <nav className="sm:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
                <div className="max-w-md mx-auto px-4">
                    <div className="flex justify-around py-2">
                        {navigationTabs.map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => handleNavigation(tab.id)}
                                className={`flex flex-col items-center p-2 ${
                                    activeTab === tab.id ? 'text-blue-600' : 'text-gray-600'
                                }`}
                            >
                                <tab.icon className="w-6 h-6" />
                                <span className="text-xs mt-1">{tab.label}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {renderContent()}
            </main>
        </div>
    );
};

export default HostLayout; 