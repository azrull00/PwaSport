import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import axios from 'axios';
import { 
    HiHome, 
    HiUserGroup, 
    HiCalendar, 
    HiLocationMarker, 
    HiChartBar,
    HiCog,
    HiUsers,
    HiClipboardList,
    HiTrendingUp,
    HiAcademicCap as HiTrophy
} from 'react-icons/hi';
import { EventProvider, useEvent } from '../../contexts/EventContext';
import HostDashboard from './HostDashboard';
import PlayerManagement from './PlayerManagement';
import GuestPlayerManagement from './GuestPlayerManagement';
import MatchmakingDashboard from './MatchmakingDashboard';
import CourtManagement from './CourtManagement';
import VenueManagement from './VenueManagement';
import CommunityManagement from './CommunityManagement';
import AdvancedAnalytics from './AdvancedAnalytics';
import TournamentManagement from './TournamentManagement';

const HostLayoutContent = ({ userToken, userData, onLogout }) => {
    const [user, setUser] = useState(userData);
    const [activeTab, setActiveTab] = useState('dashboard');
    const [currentView, setCurrentView] = useState({ page: 'dashboard', params: null });
    const [hasCommunity, setHasCommunity] = useState(false);
    const location = useLocation();
    const { currentEvent, events, loading: eventLoading } = useEvent();

    const navigationTabs = [
        { id: 'dashboard', icon: HiHome, label: 'Dashboard', path: '/host/dashboard' },
        { id: 'players', icon: HiUsers, label: 'Players', path: '/host/players' },
        { id: 'events', icon: HiCalendar, label: 'Events', path: '/host/events' },
        { id: 'analytics', icon: HiTrendingUp, label: 'Analytics', path: '/host/analytics' },
        { id: 'tournament', icon: HiTrophy, label: 'Tournament', path: '/host/tournament' },
        { id: 'venues', icon: HiLocationMarker, label: 'Venues', path: '/host/venues' },
        { id: 'community', icon: HiUserGroup, label: 'Community', path: '/host/community' }
    ];

    // Check if host has any communities
    useEffect(() => {
        const checkCommunities = async () => {
            try {
                const response = await axios.get('/communities/my-communities');
                const hasExistingCommunity = response.data.data.communities.length > 0;
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
            const response = await axios.get('/auth/profile');
            if (response.data.status === 'success') {
                setUser(response.data.data.user);
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

    const isActive = (tabId) => {
        return activeTab === tabId;
    };

    const renderContent = () => {
        // If events are loading, show loading state
        if (eventLoading && ['players', 'events', 'matchmaking'].includes(currentView.page)) {
            return (
                <div className="flex justify-center items-center h-64">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            );
        }

        switch (currentView.page) {
            case 'dashboard':
                return <HostDashboard user={user} userToken={userToken} onNavigate={handleNavigation} />;
            case 'players':
                return currentView.params?.showGuests ? (
                    <GuestPlayerManagement 
                        eventId={currentEvent?.id} 
                        userToken={userToken} 
                        onNavigate={handleNavigation}
                    />
                ) : (
                    <PlayerManagement 
                        eventId={currentEvent?.id} 
                        userToken={userToken} 
                        onNavigate={handleNavigation}
                    />
                );
            case 'events':
                return <MatchmakingDashboard userToken={userToken} onNavigate={handleNavigation} />;
            case 'analytics':
                return <AdvancedAnalytics userToken={userToken} />;
            case 'tournament':
                return <TournamentManagement userToken={userToken} />;
            case 'matchmaking':
                return (
                    <CourtManagement 
                        eventId={currentEvent?.id}
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
        <div className="min-h-screen bg-gray-50 pb-16">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center">
                        <span className="text-lg font-semibold text-gray-900">SportPWA Host</span>
                    </div>
                    <div className="flex items-center space-x-3">
                        {currentEvent && (
                            <span className="text-sm text-gray-600 hidden sm:block">
                                Event: {currentEvent.title}
                            </span>
                        )}
                        <button
                            onClick={onLogout}
                            className="text-sm text-primary font-medium hover:text-primary-dark"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <main className="pb-16">
                {renderContent()}
            </main>

            {/* Bottom Navigation */}
            <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200">
                <div className="max-w-md mx-auto px-4">
                    <div className="flex justify-around py-2">
                        {navigationTabs.map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => handleNavigation(tab.id)}
                                className={`flex flex-col items-center p-2 ${
                                    isActive(tab.id) ? 'text-blue-600' : 'text-gray-600'
                                }`}
                            >
                                <tab.icon className="w-6 h-6" />
                                <span className="text-xs mt-1">{tab.label}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </nav>
        </div>
    );
};

const HostLayout = (props) => (
    <EventProvider>
        <HostLayoutContent {...props} />
    </EventProvider>
);

export default HostLayout; 