import React, { useState, useEffect } from 'react';
import DiscoveryPage from './DiscoveryPage';
import MyEventsPage from './MyEventsPage';
import ChatPage from './ChatPage';
import ProfilePage from './ProfilePage';
import EventDetailPage from './EventDetailPage';
import CommunityDetailPage from './CommunityDetailPage';

const MainLayout = ({ userType, userToken, userData, onLogout }) => {
    const [activeTab, setActiveTab] = useState('home');
    const [user, setUser] = useState(userData);
    const [currentView, setCurrentView] = useState({ page: 'home', params: null });

    const navigationTabs = [
        { id: 'home', icon: '🏠', label: 'Beranda' },
        { id: 'discover', icon: '🔍', label: 'Jelajah' },
        { id: 'events', icon: '📅', label: 'Event Saya' },
        { id: 'chat', icon: '💬', label: 'Chat' },
        { id: 'profile', icon: '👤', label: 'Profil' }
    ];

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
        if (page === 'eventDetail' || page === 'communityDetail') {
            // For detail pages, don't change activeTab but update currentView
            setCurrentView({ page, params });
        } else {
            // For main pages, set both activeTab and currentView
            setActiveTab(page);
            setCurrentView({ page, params });
        }
    };

    const handleBack = () => {
        // Go back to the last main tab view
        setCurrentView({ page: activeTab, params: null });
    };

    const renderContent = () => {
        switch (currentView.page) {
            case 'home':
                return <HomePage user={user} userToken={userToken} onNavigate={handleNavigation} />;
            case 'discover':
                return <DiscoveryPage userToken={userToken} onNavigate={handleNavigation} />;
            case 'events':
                return <MyEventsPage user={user} userToken={userToken} onNavigate={handleNavigation} />;
            case 'chat':
                return <ChatPage user={user} userToken={userToken} />;
            case 'profile':
                return <ProfilePage user={user} userToken={userToken} onLogout={onLogout} onUserUpdate={setUser} />;
            case 'eventDetail':
                return (
                    <EventDetailPage 
                        eventId={currentView.params?.eventId} 
                        userToken={userToken} 
                        onNavigate={handleNavigation}
                        onBack={handleBack}
                    />
                );
            case 'communityDetail':
                return (
                    <CommunityDetailPage 
                        communityId={currentView.params?.communityId} 
                        userToken={userToken} 
                        onNavigate={handleNavigation}
                        onBack={handleBack}
                    />
                );
            default:
                return <HomePage user={user} userToken={userToken} onNavigate={handleNavigation} />;
        }
    };

    return (
        <div className="min-h-screen bg-secondary flex flex-col">
            {/* Content Area */}
            <div className="flex-1 pb-20">
                {renderContent()}
            </div>

            {/* Bottom Navigation - Hide on detail pages */}
            {!['eventDetail', 'communityDetail'].includes(currentView.page) && (
                <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
                    <div className="flex justify-around items-center py-2">
                        {navigationTabs.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => handleNavigation(tab.id)}
                                className={`flex flex-col items-center py-2 px-3 min-w-0 flex-1 ${
                                    activeTab === tab.id 
                                        ? 'text-primary' 
                                        : 'text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                <span className="text-xl mb-1">{tab.icon}</span>
                                <span className={`text-xs font-medium truncate ${
                                    activeTab === tab.id ? 'text-primary' : 'text-gray-500'
                                }`}>
                                    {tab.label}
                                </span>
                                {activeTab === tab.id && (
                                    <div className="w-4 h-0.5 bg-primary rounded-full mt-1"></div>
                                )}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

// HomePage Component
const HomePage = ({ user, userToken, onNavigate }) => {
    const [stats, setStats] = useState(null);
    const [nearbyEvents, setNearbyEvents] = useState([]);
    const [communities, setCommunities] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchDashboardData();
    }, [userToken]);

    const fetchDashboardData = async () => {
        try {
            setLoading(true);
            
            console.log('Frontend userToken:', userToken);
            
            // Fetch nearby events
            const eventsResponse = await fetch('/api/events?per_page=5&available_only=true', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            console.log('Events response status:', eventsResponse.status);
            
            if (eventsResponse.ok) {
                const eventsData = await eventsResponse.json();
                console.log('Events data from HomePage:', eventsData);
                console.log('eventsData.data:', eventsData.data);
                console.log('eventsData.data.events:', eventsData.data.events);
                console.log('eventsData.data.events.data:', eventsData.data ? eventsData.data.events ? eventsData.data.events.data : 'events not found' : 'data not found');
                
                if (eventsData.status === 'success') {
                    // Parse paginated events response
                    let events = [];
                    if (eventsData.data && eventsData.data.events && eventsData.data.events.data) {
                        events = eventsData.data.events.data;
                        console.log('SUCCESS: Found events array with length:', events.length);
                    } else {
                        console.log('FAILED: Events parsing condition failed');
                        console.log('- eventsData.data exists:', !!eventsData.data);
                        console.log('- eventsData.data.events exists:', !!(eventsData.data && eventsData.data.events));
                        console.log('- eventsData.data.events.data exists:', !!(eventsData.data && eventsData.data.events && eventsData.data.events.data));
                    }
                    console.log('Final parsed events:', events);
                    setNearbyEvents(events);
                }
            } else {
                console.error('Events API Error:', eventsResponse.status, eventsResponse.statusText);
                const errorText = await eventsResponse.text();
                console.error('Error response:', errorText);
            }

            // Fetch communities
            const communitiesResponse = await fetch('/api/communities?per_page=3', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            console.log('Communities response status:', communitiesResponse.status);
            
            if (communitiesResponse.ok) {
                const communitiesData = await communitiesResponse.json();
                console.log('Communities data from HomePage:', communitiesData);
                console.log('communitiesData.data:', communitiesData.data);
                console.log('communitiesData.data.communities:', communitiesData.data.communities);
                console.log('communitiesData.data.communities.data:', communitiesData.data ? communitiesData.data.communities ? communitiesData.data.communities.data : 'communities not found' : 'data not found');
                
                if (communitiesData.status === 'success') {
                    // Parse paginated communities response
                    let communities = [];
                    if (communitiesData.data && communitiesData.data.communities && communitiesData.data.communities.data) {
                        communities = communitiesData.data.communities.data;
                        console.log('SUCCESS: Found communities array with length:', communities.length);
                    } else {
                        console.log('FAILED: Communities parsing condition failed');
                        console.log('- communitiesData.data exists:', !!communitiesData.data);
                        console.log('- communitiesData.data.communities exists:', !!(communitiesData.data && communitiesData.data.communities));
                        console.log('- communitiesData.data.communities.data exists:', !!(communitiesData.data && communitiesData.data.communities && communitiesData.data.communities.data));
                    }
                    console.log('Final parsed communities:', communities);
                    setCommunities(communities);
                }
            } else {
                console.error('Communities API Error:', communitiesResponse.status, communitiesResponse.statusText);
                const errorText = await communitiesResponse.text();
                console.error('Error response:', errorText);
            }

        } catch (error) {
            console.error('Error fetching dashboard data:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                    <p className="text-gray-600 mt-2">Memuat...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="p-6">
            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">
                    Halo, {user?.profile?.first_name || user?.name || 'Player'}! 👋
                </h1>
                <p className="text-gray-600 mt-1">Selamat datang di SportApp</p>
            </div>

            {/* Quick Stats */}
            <div className="grid grid-cols-3 gap-4 mb-6">
                <div className="bg-white rounded-xl p-4 text-center shadow-sm">
                    <div className="text-2xl font-bold text-primary">{user?.credit_score || 100}</div>
                    <div className="text-xs text-gray-600">Credit Score</div>
                </div>
                <div className="bg-white rounded-xl p-4 text-center shadow-sm">
                    <div className="text-2xl font-bold text-primary">0</div>
                    <div className="text-xs text-gray-600">Event Joined</div>
                </div>
                <div className="bg-white rounded-xl p-4 text-center shadow-sm">
                    <div className="text-2xl font-bold text-primary">{communities.length}</div>
                    <div className="text-xs text-gray-600">Komunitas</div>
                </div>
            </div>

            {/* Quick Actions */}
            <div className="grid grid-cols-2 gap-4 mb-6">
                <button 
                    onClick={() => onNavigate('discover')}
                    className="bg-primary text-white rounded-xl p-4 flex items-center justify-center space-x-2 shadow-sm hover:bg-primary-dark transition-colors"
                >
                    <span>🔍</span>
                    <span className="font-semibold">Cari Event</span>
                </button>
                <button 
                    onClick={() => onNavigate('events')}
                    className="bg-white border-2 border-primary text-primary rounded-xl p-4 flex items-center justify-center space-x-2 shadow-sm hover:bg-primary-light transition-colors"
                >
                    <span>📅</span>
                    <span className="font-semibold">Event Saya</span>
                </button>
            </div>

            {/* Nearby Events */}
            <div className="mb-6">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold text-gray-900">Event Terdekat</h2>
                    <button 
                        onClick={() => onNavigate('discover')}
                        className="text-primary text-sm font-medium hover:underline"
                    >
                        Lihat Semua
                    </button>
                </div>
                
                {nearbyEvents.length > 0 ? (
                    <div className="space-y-3">
                        {nearbyEvents.slice(0, 3).map((event) => (
                            <div key={event.id} className="bg-white rounded-xl p-4 shadow-sm">
                                <div className="flex justify-between items-start">
                                    <div className="flex-1">
                                        <h3 className="font-semibold text-gray-900">{event.title}</h3>
                                        <p className="text-sm text-gray-600">{event.sport?.name}</p>
                                        <p className="text-sm text-gray-500">{new Date(event.event_date).toLocaleDateString('id-ID')}</p>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-sm text-primary font-medium">
                                            {event.available_slots} slot
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="bg-white rounded-xl p-6 text-center shadow-sm">
                        <p className="text-gray-500">Belum ada event terdekat</p>
                    </div>
                )}
            </div>

            {/* Communities */}
            <div>
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold text-gray-900">Komunitas Populer</h2>
                    <button 
                        onClick={() => {
                            onNavigate('discover');
                            // Note: We'll need to set active tab to communities in DiscoveryPage
                        }}
                        className="text-primary text-sm font-medium hover:underline"
                    >
                        Lihat Semua
                    </button>
                </div>
                
                {communities.length > 0 ? (
                    <div className="space-y-3">
                        {communities.map((community) => (
                            <div key={community.id} className="bg-white rounded-xl p-4 shadow-sm">
                                <div className="flex items-center space-x-3">
                                    <div className="w-12 h-12 bg-primary rounded-full flex items-center justify-center">
                                        <span className="text-white font-bold">{community.name.charAt(0)}</span>
                                    </div>
                                    <div className="flex-1">
                                        <h3 className="font-semibold text-gray-900">{community.name}</h3>
                                        <p className="text-sm text-gray-600">{community.sport?.name}</p>
                                        <p className="text-xs text-gray-500">{community.member_count} anggota</p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="bg-white rounded-xl p-6 text-center shadow-sm">
                        <p className="text-gray-500">Belum ada komunitas</p>
                    </div>
                )}
            </div>
        </div>
    );
};



export default MainLayout; 