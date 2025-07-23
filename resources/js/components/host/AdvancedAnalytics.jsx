import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { 
    HiTrendingUp, 
    HiUsers, 
    HiCalendar, 
    HiLocationMarker,
    HiClock,
    HiChartBar,
    HiRefresh
} from 'react-icons/hi';

const AdvancedAnalytics = ({ userToken }) => {
    const [activeTab, setActiveTab] = useState('overview');
    const [timeframe, setTimeframe] = useState('30days');
    const [loading, setLoading] = useState(true);
    const [analytics, setAnalytics] = useState(null);
    const [venues, setVenues] = useState([]);
    const [communities, setCommunities] = useState([]);
    const [selectedVenue, setSelectedVenue] = useState(null);
    const [selectedCommunity, setSelectedCommunity] = useState(null);
    const [refreshing, setRefreshing] = useState(false);

    const timeframeOptions = [
        { value: '7days', label: '7 Hari' },
        { value: '30days', label: '30 Hari' },
        { value: '90days', label: '90 Hari' }
    ];

    useEffect(() => {
        loadAnalytics();
        loadVenues();
        loadCommunities();
    }, [timeframe]);

    const loadAnalytics = async () => {
        setLoading(true);
        try {
            const response = await axios.get(`/host/analytics?timeframe=${timeframe}`, {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setAnalytics(response.data.data.analytics);
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadVenues = async () => {
        try {
            const response = await axios.get('/host/venues', {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setVenues(response.data.data || []);
                if (response.data.data?.length > 0) {
                    setSelectedVenue(response.data.data[0]);
                }
            }
        } catch (error) {
            console.error('Error loading venues:', error);
        }
    };

    const loadCommunities = async () => {
        try {
            const response = await axios.get('/communities/my-communities', {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setCommunities(response.data.data.communities || []);
                if (response.data.data.communities?.length > 0) {
                    setSelectedCommunity(response.data.data.communities[0]);
                }
            }
        } catch (error) {
            console.error('Error loading communities:', error);
        }
    };

    const refreshData = async () => {
        setRefreshing(true);
        await Promise.all([loadAnalytics(), loadVenues(), loadCommunities()]);
        setRefreshing(false);
    };

    const MetricCard = ({ icon: Icon, title, value, subtitle, color = 'primary', trend = null }) => (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <div className={`p-3 bg-${color}/10 rounded-lg`}>
                        <Icon className={`w-6 h-6 text-${color}`} />
                    </div>
                    <div>
                        <h3 className="text-sm font-medium text-gray-600">{title}</h3>
                        <p className="text-2xl font-bold text-gray-900">{value}</p>
                        {subtitle && (
                            <p className="text-sm text-gray-500">{subtitle}</p>
                        )}
                    </div>
                </div>
                {trend && (
                    <div className={`text-sm font-medium ${trend > 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {trend > 0 ? '↗' : '↘'} {Math.abs(trend)}%
                    </div>
                )}
            </div>
        </div>
    );

    const ChartCard = ({ title, children }) => (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">{title}</h3>
            {children}
        </div>
    );

    const OverviewTab = () => (
        <div className="space-y-6">
            {/* Key Metrics */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <MetricCard
                    icon={HiCalendar}
                    title="Total Events"
                    value={analytics?.eventStats?.total || 0}
                    subtitle={`${analytics?.eventStats?.active || 0} aktif`}
                    trend={analytics?.growth?.events || 0}
                />
                <MetricCard
                    icon={HiUsers}
                    title="Total Member"
                    value={analytics?.communityStats?.totalMembers || 0}
                    subtitle="Semua komunitas"
                    color="blue"
                    trend={analytics?.growth?.members || 0}
                />
                <MetricCard
                    icon={HiLocationMarker}
                    title="Venue"
                    value={venues.length}
                    subtitle={`${venues.filter(v => v.status === 'active').length} aktif`}
                    color="green"
                />
                <MetricCard
                    icon={HiTrendingUp}
                    title="Participation Rate"
                    value={`${Math.round(analytics?.participationRate || 0)}%`}
                    subtitle="Average"
                    color="purple"
                />
            </div>

            {/* Event Status Distribution */}
            <ChartCard title="Status Event">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="text-center p-4 bg-green-50 rounded-lg">
                        <div className="text-2xl font-bold text-green-600">
                            {analytics?.eventStats?.active || 0}
                        </div>
                        <div className="text-sm text-green-700">Aktif</div>
                    </div>
                    <div className="text-center p-4 bg-blue-50 rounded-lg">
                        <div className="text-2xl font-bold text-blue-600">
                            {analytics?.eventStats?.completed || 0}
                        </div>
                        <div className="text-sm text-blue-700">Selesai</div>
                    </div>
                    <div className="text-center p-4 bg-red-50 rounded-lg">
                        <div className="text-2xl font-bold text-red-600">
                            {analytics?.eventStats?.cancelled || 0}
                        </div>
                        <div className="text-sm text-red-700">Dibatalkan</div>
                    </div>
                    <div className="text-center p-4 bg-gray-50 rounded-lg">
                        <div className="text-2xl font-bold text-gray-600">
                            {analytics?.eventStats?.total || 0}
                        </div>
                        <div className="text-sm text-gray-700">Total</div>
                    </div>
                </div>
            </ChartCard>

            {/* Community Overview */}
            <ChartCard title="Overview Komunitas">
                <div className="space-y-4">
                    {communities.map(community => (
                        <div key={community.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div className="flex items-center space-x-3">
                                <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                                    <span className="text-primary font-semibold">
                                        {community.name.charAt(0)}
                                    </span>
                                </div>
                                <div>
                                    <h4 className="font-medium text-gray-900">{community.name}</h4>
                                    <p className="text-sm text-gray-600">{community.sport?.name}</p>
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-lg font-semibold text-gray-900">
                                    {community.member_count || 0}
                                </div>
                                <div className="text-sm text-gray-600">member</div>
                            </div>
                        </div>
                    ))}
                </div>
            </ChartCard>
        </div>
    );

    const VenueAnalyticsTab = () => (
        <div className="space-y-6">
            {/* Venue Selector */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    Pilih Venue
                </label>
                <select
                    value={selectedVenue?.id || ''}
                    onChange={(e) => {
                        const venue = venues.find(v => v.id === parseInt(e.target.value));
                        setSelectedVenue(venue);
                    }}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                    {venues.map(venue => (
                        <option key={venue.id} value={venue.id}>
                            {venue.name}
                        </option>
                    ))}
                </select>
            </div>

            {selectedVenue && (
                <VenueUtilizationReport venue={selectedVenue} userToken={userToken} />
            )}
        </div>
    );

    const CommunityAnalyticsTab = () => (
        <div className="space-y-6">
            {/* Community Selector */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    Pilih Komunitas
                </label>
                <select
                    value={selectedCommunity?.id || ''}
                    onChange={(e) => {
                        const community = communities.find(c => c.id === parseInt(e.target.value));
                        setSelectedCommunity(community);
                    }}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                    {communities.map(community => (
                        <option key={community.id} value={community.id}>
                            {community.name}
                        </option>
                    ))}
                </select>
            </div>

            {selectedCommunity && (
                <CommunityEngagementMetrics community={selectedCommunity} userToken={userToken} />
            )}
        </div>
    );

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        );
    }

    return (
        <div className="p-4 space-y-4">
            {/* Header */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div className="flex items-center justify-between mb-4">
                    <h1 className="text-lg font-semibold text-gray-900">Advanced Analytics</h1>
                    <button
                        onClick={refreshData}
                        className={`p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 ${
                            refreshing ? 'animate-spin' : ''
                        }`}
                    >
                        <HiRefresh className="w-5 h-5" />
                    </button>
                </div>

                {/* Timeframe & Tabs */}
                <div className="flex flex-col sm:flex-row gap-4">
                    <select
                        value={timeframe}
                        onChange={(e) => setTimeframe(e.target.value)}
                        className="px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                        {timeframeOptions.map(option => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>

                    <div className="flex bg-gray-100 rounded-xl p-1">
                        <button
                            onClick={() => setActiveTab('overview')}
                            className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors text-sm ${
                                activeTab === 'overview'
                                    ? 'bg-white text-primary shadow-sm'
                                    : 'text-gray-600 hover:text-gray-800'
                            }`}
                        >
                            Overview
                        </button>
                        <button
                            onClick={() => setActiveTab('venues')}
                            className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors text-sm ${
                                activeTab === 'venues'
                                    ? 'bg-white text-primary shadow-sm'
                                    : 'text-gray-600 hover:text-gray-800'
                            }`}
                        >
                            Venue
                        </button>
                        <button
                            onClick={() => setActiveTab('communities')}
                            className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors text-sm ${
                                activeTab === 'communities'
                                    ? 'bg-white text-primary shadow-sm'
                                    : 'text-gray-600 hover:text-gray-800'
                            }`}
                        >
                            Komunitas
                        </button>
                    </div>
                </div>
            </div>

            {/* Content */}
            {activeTab === 'overview' && <OverviewTab />}
            {activeTab === 'venues' && <VenueAnalyticsTab />}
            {activeTab === 'communities' && <CommunityAnalyticsTab />}
        </div>
    );
};

// Venue Utilization Report Component
const VenueUtilizationReport = ({ venue, userToken }) => {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadVenueStats();
    }, [venue.id]);

    const loadVenueStats = async () => {
        setLoading(true);
        try {
            const response = await axios.get(`/host/venues/${venue.id}/stats`, {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setStats(response.data.data);
            }
        } catch (error) {
            console.error('Error loading venue stats:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center h-32">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Utilization Metrics */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-sm font-medium text-gray-600 mb-2">Capacity Utilization</h3>
                    <div className="flex items-center space-x-2">
                        <div className="text-2xl font-bold text-blue-600">
                            {stats?.utilization?.capacity_utilization || 0}%
                        </div>
                        <div className="flex-1 bg-gray-200 rounded-full h-2">
                            <div 
                                className="bg-blue-600 h-2 rounded-full"
                                style={{ width: `${Math.min(stats?.utilization?.capacity_utilization || 0, 100)}%` }}
                            ></div>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-sm font-medium text-gray-600 mb-2">Court Utilization</h3>
                    <div className="flex items-center space-x-2">
                        <div className="text-2xl font-bold text-green-600">
                            {stats?.utilization?.court_utilization || 0}%
                        </div>
                        <div className="flex-1 bg-gray-200 rounded-full h-2">
                            <div 
                                className="bg-green-600 h-2 rounded-full"
                                style={{ width: `${Math.min(stats?.utilization?.court_utilization || 0, 100)}%` }}
                            ></div>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-sm font-medium text-gray-600 mb-2">Completion Rate</h3>
                    <div className="flex items-center space-x-2">
                        <div className="text-2xl font-bold text-purple-600">
                            {stats?.statistics?.completion_rate || 0}%
                        </div>
                        <div className="flex-1 bg-gray-200 rounded-full h-2">
                            <div 
                                className="bg-purple-600 h-2 rounded-full"
                                style={{ width: `${Math.min(stats?.statistics?.completion_rate || 0, 100)}%` }}
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Event Statistics */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Event Statistics</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="text-center p-4 bg-blue-50 rounded-lg">
                        <div className="text-2xl font-bold text-blue-600">
                            {stats?.statistics?.total_events || 0}
                        </div>
                        <div className="text-sm text-blue-700">Total Events</div>
                    </div>
                    <div className="text-center p-4 bg-green-50 rounded-lg">
                        <div className="text-2xl font-bold text-green-600">
                            {stats?.statistics?.events_this_week || 0}
                        </div>
                        <div className="text-sm text-green-700">Minggu Ini</div>
                    </div>
                    <div className="text-center p-4 bg-purple-50 rounded-lg">
                        <div className="text-2xl font-bold text-purple-600">
                            {stats?.statistics?.total_participants || 0}
                        </div>
                        <div className="text-sm text-purple-700">Total Participant</div>
                    </div>
                    <div className="text-center p-4 bg-orange-50 rounded-lg">
                        <div className="text-2xl font-bold text-orange-600">
                            {stats?.statistics?.total_matches || 0}
                        </div>
                        <div className="text-sm text-orange-700">Total Match</div>
                    </div>
                </div>
            </div>

            {/* Upcoming Events */}
            {stats?.upcoming_events?.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Upcoming Events</h3>
                    <div className="space-y-3">
                        {stats.upcoming_events.map(event => (
                            <div key={event.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <h4 className="font-medium text-gray-900">{event.title}</h4>
                                    <p className="text-sm text-gray-600">
                                        {event.event_date} • {event.participants_count}/{event.max_participants} peserta
                                    </p>
                                </div>
                                <div className="text-sm text-gray-500">
                                    {event.start_time}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

// Community Engagement Metrics Component
const CommunityEngagementMetrics = ({ community, userToken }) => {
    const [stats, setStats] = useState(null);
    const [players, setPlayers] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadCommunityStats();
        loadCommunityPlayers();
    }, [community.id]);

    const loadCommunityStats = async () => {
        try {
            const response = await axios.get(`/host/communities/${community.id}/stats`, {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setStats(response.data.data.statistics);
            }
        } catch (error) {
            console.error('Error loading community stats:', error);
        }
    };

    const loadCommunityPlayers = async () => {
        try {
            const response = await axios.get(`/api/communities/${community.id}/players`, {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setPlayers(response.data.data.players);
            }
        } catch (error) {
            console.error('Error loading community players:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center h-32">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Engagement Metrics */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-sm font-medium text-gray-600 mb-2">Active Members</h3>
                    <div className="text-2xl font-bold text-green-600">
                        {stats?.active_members || 0}
                    </div>
                    <div className="text-sm text-gray-500">
                        dari {stats?.total_members || 0} total
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-sm font-medium text-gray-600 mb-2">Participation Rate</h3>
                    <div className="text-2xl font-bold text-blue-600">
                        {stats?.participation_rate || 0}%
                    </div>
                    <div className="text-sm text-gray-500">average per event</div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-sm font-medium text-gray-600 mb-2">Upcoming Events</h3>
                    <div className="text-2xl font-bold text-purple-600">
                        {stats?.upcoming_events || 0}
                    </div>
                    <div className="text-sm text-gray-500">scheduled</div>
                </div>
            </div>

            {/* Top Players */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Top Active Players</h3>
                <div className="space-y-3">
                    {players
                        .sort((a, b) => b.events_participated - a.events_participated)
                        .slice(0, 5)
                        .map((player, index) => (
                            <div key={player.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div className="flex items-center space-x-3">
                                    <div className="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                        <span className="text-primary font-semibold text-sm">
                                            #{index + 1}
                                        </span>
                                    </div>
                                    <div>
                                        <h4 className="font-medium text-gray-900">{player.name}</h4>
                                        <p className="text-sm text-gray-600">
                                            {player.events_participated} events • Win Rate: {Math.round(player.win_rate * 100)}%
                                        </p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-lg font-semibold text-gray-900">
                                        {player.rating}
                                    </div>
                                    <div className="text-sm text-gray-600">MMR</div>
                                </div>
                            </div>
                        ))}
                </div>
            </div>
        </div>
    );
};

export default AdvancedAnalytics;