import React, { useState, useEffect } from 'react';
import { HiHome, HiSearch, HiCalendar, HiUsers, HiChatAlt2, HiUser, HiTrendingUp, HiStar, HiChevronRight, HiLocationMarker, HiClock } from 'react-icons/hi';
import { HiMiniArrowLeft } from 'react-icons/hi2';
import { Routes, Route, Link, useLocation, useNavigate } from 'react-router-dom';
import DiscoveryPage from './DiscoveryPage';
import MyEventsPage from './MyEventsPage';
import ChatPage from './ChatPage';
import ProfilePage from './ProfilePage';
import EventDetailPage from './EventDetailPage';
import CommunityDetailPage from './CommunityDetailPage';
import MatchmakingStatusPage from './MatchmakingStatusPage';
import MatchHistoryPage from './MatchHistoryPage';
import CourtManagementPage from './CourtManagementPage';
import FriendsPage from './FriendsPage';

const MainLayout = ({ userType, userToken, userData, onLogout }) => {
    const [user, setUser] = useState(userData);
    const location = useLocation();
    const navigate = useNavigate();

    const navigationTabs = [
        { id: 'home', icon: HiHome, label: 'Beranda', path: '/player' },
        { id: 'discover', icon: HiSearch, label: 'Jelajah', path: '/player/discover' },
        { id: 'events', icon: HiCalendar, label: 'Event Saya', path: '/player/events' },
        { id: 'chat', icon: HiChatAlt2, label: 'Chat & Teman', path: '/player/chat' },
        { id: 'profile', icon: HiUser, label: 'Profil', path: '/player/profile' }
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

    const handleNavigation = (path, params = {}) => {
        // Handle special navigation cases
        if (path === 'matchmakingStatus') {
            navigate('/player/matchmakingStatus', { state: params });
        } else if (path.startsWith('/')) {
            navigate(path, { state: params });
        } else {
            navigate(`/player/${path}`, { state: params });
        }
    };

    const handleBack = () => {
        navigate(-1);
    };

    const isActive = (path) => {
        return location.pathname === path;
    };

    return (
        <div className="min-h-screen bg-gray-50 pb-16">
            {/* Main Content */}
            <main className="pb-16">
                <Routes>
                    <Route path="/" element={<HomePage user={user} userToken={userToken} onNavigate={handleNavigation} />} />
                    <Route path="/discover" element={<DiscoveryPage userToken={userToken} onNavigate={handleNavigation} />} />
                    <Route path="/events" element={<MyEventsPage user={user} userToken={userToken} onNavigate={handleNavigation} />} />
                    <Route path="/chat" element={
                        <div>
                            <div className="flex justify-between items-center p-4 bg-white shadow">
                                <button
                                    className={`flex-1 py-2 px-4 text-center ${location.state?.showFriends ? 'text-gray-600' : 'text-primary border-b-2 border-primary'}`}
                                    onClick={() => navigate('/player/chat', { state: { showFriends: false } })}
                                >
                                    <HiChatAlt2 className="w-6 h-6 mx-auto" />
                                    <span className="text-sm">Chat</span>
                                </button>
                                <button
                                    className={`flex-1 py-2 px-4 text-center ${location.state?.showFriends ? 'text-primary border-b-2 border-primary' : 'text-gray-600'}`}
                                    onClick={() => navigate('/player/chat', { state: { showFriends: true } })}
                                >
                                    <HiUsers className="w-6 h-6 mx-auto" />
                                    <span className="text-sm">Teman</span>
                                </button>
                            </div>
                            {location.state?.showFriends ? (
                                <FriendsPage 
                                    userToken={userToken} 
                                    onNavigate={handleNavigation}
                                    onStartPrivateChat={(userId) => {
                                        navigate('/player/chat', { state: { showFriends: false } });
                                        setTimeout(() => {
                                            window.dispatchEvent(new CustomEvent('startPrivateChat', { detail: { userId } }));
                                        }, 100);
                                    }}
                                />
                            ) : (
                                <ChatPage user={user} userToken={userToken} />
                            )}
                        </div>
                    } />
                    <Route path="/profile" element={
                        <ProfilePage 
                            user={user} 
                            userToken={userToken} 
                            onLogout={onLogout} 
                            onUserUpdate={setUser} 
                            onNavigate={handleNavigation}
                            onStartPrivateChat={(userId) => {
                                navigate('/player/chat', { state: { showFriends: false } });
                                setTimeout(() => {
                                    window.dispatchEvent(new CustomEvent('startPrivateChat', { detail: { userId } }));
                                }, 100);
                            }}
                        />
                    } />
                    <Route path="/event/:eventId" element={
                        <EventDetailPage 
                            userToken={userToken} 
                            onNavigate={handleNavigation}
                            onBack={handleBack}
                        />
                    } />
                    <Route path="/community/:communityId" element={
                        <CommunityDetailPage 
                            userToken={userToken} 
                            onNavigate={handleNavigation}
                            onBack={handleBack}
                        />
                    } />
                    <Route path="/matchmakingStatus" element={
                        <MatchmakingStatusPage 
                            userToken={userToken} 
                            onNavigate={handleNavigation}
                            onBack={handleBack}
                        />
                    } />
                    <Route path="/matchmaking/:eventId" element={
                        <MatchmakingStatusPage 
                            userToken={userToken} 
                            onNavigate={handleNavigation}
                            onBack={handleBack}
                        />
                    } />
                    <Route path="/match-history" element={
                        <MatchHistoryPage 
                            userToken={userToken} 
                            onNavigate={handleNavigation}
                            onBack={handleBack}
                        />
                    } />
                    <Route path="/court-management/:eventId" element={
                        <CourtManagementPage 
                            userToken={userToken}
                            userType={userType}
                            onNavigate={handleNavigation}
                            onBack={handleBack}
                        />
                    } />
                </Routes>
            </main>

            {/* Bottom Navigation */}
            <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200">
                <div className="max-w-md mx-auto px-4">
                    <div className="flex justify-around py-2">
                        {navigationTabs.map(tab => (
                            <Link
                                key={tab.id}
                                to={tab.path}
                                className={`flex flex-col items-center p-2 ${
                                    isActive(tab.path) ? 'text-blue-600' : 'text-gray-600'
                                }`}
                            >
                                <tab.icon className="w-6 h-6" />
                                <span className="text-xs mt-1">{tab.label}</span>
                            </Link>
                        ))}
                    </div>
                </div>
            </nav>
        </div>
    );
};

// HomePage Component
const HomePage = ({ user, userToken, onNavigate }) => {
    const [stats, setStats] = useState(null);
    const [nearbyEvents, setNearbyEvents] = useState([]);
    const [communities, setCommunities] = useState([]);
    const [loading, setLoading] = useState(true);
    const [currentBadgeIndex, setCurrentBadgeIndex] = useState(0);
    
    const badges = [
        {
            id: 1,
            title: "Sport Champions League",
            subtitle: "Bergabung dengan turnamen terbesar",
            image: "/storage/AppImages/placeholder1.png",
            action: "Daftar Sekarang",
            color: "from-blue-500 to-blue-700"
        },
        {
            id: 2,
            title: "Komunitas Sport Terbaru",
            subtitle: "Temukan teman bermain di sekitar Anda",
            image: "/storage/AppImages/placeholder2.png",
            action: "Jelajahi",
            color: "from-green-500 to-green-700"
        },
        {
            id: 3,
            title: "Event Premium",
            subtitle: "Dapatkan pengalaman bermain terbaik",
            image: "/storage/AppImages/placeholder3.png",
            action: "Lihat Detail",
            color: "from-purple-500 to-purple-700"
        }
    ];

    useEffect(() => {
        fetchDashboardData();
    }, [userToken]);

    // Auto-slide badges every 5 seconds
    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentBadgeIndex((prevIndex) => 
                prevIndex === badges.length - 1 ? 0 : prevIndex + 1
            );
        }, 5000);

        return () => clearInterval(interval);
    }, [badges.length]);

    const fetchDashboardData = async () => {
        try {
            setLoading(true);
            
            console.log('Frontend userToken:', userToken);
            
            // Fetch user stats (joined events count)
            const statsResponse = await fetch('/api/users/my-events?per_page=1', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            if (statsResponse.ok) {
                const statsData = await statsResponse.json();
                if (statsData.status === 'success') {
                    let joinedEventsCount = 0;
                    if (statsData.data.events) {
                        if (Array.isArray(statsData.data.events)) {
                            joinedEventsCount = statsData.data.events.length;
                        } else if (statsData.data.events.total) {
                            joinedEventsCount = statsData.data.events.total;
                        }
                    }
                    setStats({ joinedEvents: joinedEventsCount });
                }
            }
            
            // Fetch nearby events (recommendations)
            const eventsResponse = await fetch('/api/events/recommendations?limit=5', {
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
                
                if (eventsData.status === 'success') {
                    // Parse recommendations response
                    let events = [];
                    if (eventsData.data && eventsData.data.recommendations) {
                        events = eventsData.data.recommendations.map(rec => rec.event);
                        console.log('SUCCESS: Found recommended events with length:', events.length);
                    } else if (eventsData.data && eventsData.data.nearby_events) {
                        events = eventsData.data.nearby_events;
                        console.log('SUCCESS: Found nearby events with length:', events.length);
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
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <h1 className="text-lg font-semibold text-gray-900">
                    Halo, {user?.profile?.first_name || user?.name || 'Player'}! 👋
                </h1>
                <p className="text-gray-600 text-sm mt-1">Selamat datang di SportApp</p>
            </div>

            <div className="p-4">
                {/* Badges Slider */}
                <div className="mb-6">
                    <div className="relative overflow-hidden rounded-xl shadow-sm">
                        <div 
                            className="flex badge-slide-transition"
                            style={{ transform: `translateX(-${currentBadgeIndex * 100}%)` }}
                        >
                            {badges.map((badge, index) => (
                                <div
                                    key={badge.id}
                                    className="w-full flex-shrink-0 relative"
                                >
                                    <div className={`bg-gradient-to-r ${badge.color} animated-gradient p-6 text-white relative overflow-hidden`}>
                                        {/* Background Pattern */}
                                        <div className="absolute inset-0 opacity-10">
                                            <div className="absolute -top-4 -right-4 w-32 h-32 bg-white rounded-full"></div>
                                            <div className="absolute -bottom-6 -left-6 w-24 h-24 bg-white rounded-full"></div>
                                        </div>
                                        
                                        <div className="relative z-10 flex items-center justify-between">
                                            <div className="flex-1">
                                                <h3 className="text-xl font-bold mb-2">{badge.title}</h3>
                                                <p className="text-white/90 mb-4 text-sm">{badge.subtitle}</p>
                                                <button 
                                                    onClick={() => onNavigate('discover')}
                                                    className="bg-white/20 backdrop-blur-sm text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-white/30 transition-colors flex items-center"
                                                >
                                                    {badge.action}
                                                    <HiChevronRight className="w-4 h-4 ml-1" />
                                                </button>
                                            </div>
                                            <div className="ml-4 opacity-60">
                                                <div className="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                                                    <HiStar className="w-8 h-8" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                        
                        {/* Slider Dots */}
                        <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
                            {badges.map((_, index) => (
                                <button
                                    key={index}
                                    onClick={() => setCurrentBadgeIndex(index)}
                                    className={`w-2 h-2 rounded-full transition-colors ${
                                        index === currentBadgeIndex ? 'bg-white' : 'bg-white/40'
                                    }`}
                                />
                            ))}
                        </div>
                    </div>
                </div>

                {/* Quick Stats */}
                <div className="grid grid-cols-3 gap-3 mb-6">
                    <div className="stat-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition-shadow dashboard-card">
                        <div className="stat-card-icon w-10 h-10 bg-primary rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <HiTrendingUp className="w-5 h-5 text-white" />
                        </div>
                        <div className="text-xl font-bold text-gray-900">{user?.credit_score || 100}</div>
                        <div className="text-xs text-gray-600">Credit Score</div>
                    </div>
                    <div 
                        className="stat-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center cursor-pointer hover:shadow-md hover:border-primary/20 transition-all dashboard-card"
                        onClick={() => onNavigate('events')}
                    >
                        <div className="stat-card-icon w-10 h-10 bg-primary rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <HiCalendar className="w-5 h-5 text-white" />
                        </div>
                        <div className="text-xl font-bold text-gray-900">{stats?.joinedEvents || 0}</div>
                    <div className="text-xs text-gray-600">Event Joined</div>
                </div>
                    <div className="stat-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hover:shadow-md transition-shadow dashboard-card">
                        <div className="stat-card-icon w-10 h-10 bg-primary rounded-lg mx-auto mb-3 flex items-center justify-center">
                            <HiUsers className="w-5 h-5 text-white" />
                        </div>
                        <div className="text-xl font-bold text-gray-900">{communities.length}</div>
                    <div className="text-xs text-gray-600">Komunitas</div>
                </div>
            </div>

            {/* Quick Actions */}
                <div className="grid grid-cols-3 gap-3 mb-6">
                <button 
                    onClick={() => onNavigate('discover')}
                        className="quick-action-btn bg-primary text-white rounded-xl p-4 flex flex-col items-center justify-center space-y-2 shadow-sm hover:shadow-md hover:bg-primary-dark transition-all"
                >
                        <HiSearch className="w-5 h-5" />
                        <span className="font-medium text-xs">Cari Event</span>
                </button>
                <button 
                    onClick={() => onNavigate('events')}
                        className="quick-action-btn bg-white border border-primary text-primary rounded-xl p-4 flex flex-col items-center justify-center space-y-2 shadow-sm hover:shadow-md hover:bg-primary/5 transition-all"
                >
                        <HiCalendar className="w-5 h-5" />
                        <span className="font-medium text-xs">Event Saya</span>
                </button>
                    <button 
                        onClick={() => onNavigate('matchmakingStatus')}
                        className="quick-action-btn bg-primary text-white rounded-xl p-4 flex flex-col items-center justify-center space-y-2 shadow-sm hover:shadow-md hover:bg-primary-dark transition-all"
                    >
                        <HiTrendingUp className="w-5 h-5" />
                        <span className="font-medium text-xs">Matchmaking</span>
                    </button>
                </div>

                {/* Recommended Events */}
                <div className="mb-6">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-lg font-semibold text-gray-900 flex items-center">
                            <HiStar className="w-5 h-5 mr-2 text-primary" />
                            Rekomendasi untuk Anda
                        </h2>
                        <button 
                            onClick={() => onNavigate('discover')}
                            className="text-primary text-sm font-medium hover:underline flex items-center"
                    >
                        Lihat Semua
                            <HiChevronRight className="w-4 h-4 ml-1" />
                    </button>
                </div>
                
                {nearbyEvents.length > 0 ? (
                        <div className="space-y-3 event-list">
                        {nearbyEvents.slice(0, 3).map((event) => (
                                <div 
                                    key={event.id} 
                                    className="event-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:border-primary/20 transition-all cursor-pointer"
                                    onClick={() => onNavigate('eventDetail', { eventId: event.id })}
                                >
                                <div className="flex justify-between items-start">
                                    <div className="flex-1">
                                            <h3 className="font-semibold text-gray-900 mb-2">{event.title}</h3>
                                            
                                            {/* Event Info Grid */}
                                            <div className="grid grid-cols-2 gap-2 mb-3">
                                                <div className="flex items-center text-sm text-gray-600">
                                                    <span className="mr-2 text-primary">🏸</span>
                                                    <span className="font-medium">{event.sport?.name}</span>
                                                </div>
                                                <div className="flex items-center text-sm text-gray-600">
                                                    <HiUsers className="w-4 h-4 mr-2 text-primary" />
                                                    <span>{event.max_participants} peserta</span>
                                                </div>
                                            </div>
                                            
                                            <div className="flex items-center text-sm text-gray-500 mb-2">
                                                <HiCalendar className="w-4 h-4 mr-2" />
                                                <span>{new Date(event.event_date).toLocaleDateString('id-ID')}</span>
                                    </div>
                                            <div className="flex items-center text-sm text-gray-500">
                                                <HiLocationMarker className="w-4 h-4 mr-2" />
                                                <span className="truncate">{event.location_name}</span>
                                            </div>
                                        </div>
                                        <div className="text-right ml-4">
                                            <div className="text-sm font-bold text-primary mb-1">
                                                {event.entry_fee ? `Rp ${parseInt(event.entry_fee).toLocaleString()}` : 'GRATIS'}
                                            </div>
                                            <div className="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded">
                                                {event.available_slots || event.max_participants} slot
                                            </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center dashboard-card">
                            <div className="w-12 h-12 bg-gray-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                                <HiSearch className="w-6 h-6 text-gray-400" />
                            </div>
                            <p className="text-gray-600 mb-3">Belum ada rekomendasi event</p>
                            <button 
                                onClick={() => onNavigate('discover')}
                                className="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors"
                            >
                                Jelajahi Event
                            </button>
                    </div>
                )}
            </div>

            {/* Communities */}
            <div>
                <div className="flex justify-between items-center mb-4">
                        <h2 className="text-lg font-semibold text-gray-900 flex items-center">
                            <HiUsers className="w-5 h-5 mr-2 text-primary" />
                            Komunitas Populer
                        </h2>
                    <button 
                        onClick={() => {
                            onNavigate('discover');
                        }}
                            className="text-primary text-sm font-medium hover:underline flex items-center"
                    >
                        Lihat Semua
                            <HiChevronRight className="w-4 h-4 ml-1" />
                    </button>
                </div>
                
                {communities.length > 0 ? (
                    <div className="space-y-3">
                        {communities.map((community) => (
                                <div 
                                    key={community.id} 
                                    className="community-card bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:border-primary/20 transition-all cursor-pointer"
                                    onClick={() => onNavigate('communityDetail', { communityId: community.id })}
                                >
                                <div className="flex items-center space-x-3">
                                        <div className="w-12 h-12 bg-primary rounded-lg flex items-center justify-center">
                                            <span className="text-white font-bold text-lg">{community.name.charAt(0)}</span>
                                    </div>
                                    <div className="flex-1">
                                        <h3 className="font-semibold text-gray-900">{community.name}</h3>
                                            <div className="flex items-center text-sm text-gray-600 mt-1">
                                                <span className="mr-2 text-primary">🏸</span>
                                                <span className="font-medium">{community.sport?.name}</span>
                                            </div>
                                            <div className="flex items-center text-xs text-gray-500 mt-1">
                                                <HiUsers className="w-3 h-3 mr-1" />
                                                <span>{community.member_count} anggota</span>
                                    </div>
                                        </div>
                                        <HiChevronRight className="w-5 h-5 text-gray-400" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center dashboard-card">
                            <div className="w-12 h-12 bg-gray-100 rounded-lg mx-auto mb-3 flex items-center justify-center">
                                <HiUsers className="w-6 h-6 text-gray-400" />
                            </div>
                            <p className="text-gray-600 mb-3">Belum ada komunitas</p>
                            <button 
                                onClick={() => onNavigate('discover')}
                                className="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors"
                            >
                                Jelajahi Komunitas
                            </button>
                    </div>
                )}
                </div>
            </div>
        </div>
    );
};

export default MainLayout;