import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { 
    HiUsers, 
    HiTrendingUp, 
    HiTrendingDown,
    HiStar,
    HiClock,
    HiTrophy,
    HiRefresh,
    HiSearch,
    HiFilter
} from 'react-icons/hi';

const PlayerPerformanceAnalytics = ({ userToken, communityId = null }) => {
    const [players, setPlayers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState('rating');
    const [sortOrder, setSortOrder] = useState('desc');
    const [selectedPlayer, setSelectedPlayer] = useState(null);
    const [playerDetails, setPlayerDetails] = useState(null);
    const [communities, setCommunities] = useState([]);
    const [selectedCommunity, setSelectedCommunity] = useState(communityId);

    useEffect(() => {
        loadCommunities();
    }, []);

    useEffect(() => {
        if (selectedCommunity) {
            loadPlayers();
        }
    }, [selectedCommunity, sortBy, sortOrder]);

    const loadCommunities = async () => {
        try {
            const response = await axios.get('/communities/my-communities', {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setCommunities(response.data.data.communities || []);
                if (!selectedCommunity && response.data.data.communities?.length > 0) {
                    setSelectedCommunity(response.data.data.communities[0].id);
                }
            }
        } catch (error) {
            console.error('Error loading communities:', error);
        }
    };

    const loadPlayers = async () => {
        setLoading(true);
        try {
            const response = await axios.get(`/api/communities/${selectedCommunity}/players`, {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                let playersData = response.data.data.players || [];
                
                // Sort players
                playersData.sort((a, b) => {
                    let aValue = a[sortBy];
                    let bValue = b[sortBy];
                    
                    if (sortBy === 'name') {
                        aValue = a.name.toLowerCase();
                        bValue = b.name.toLowerCase();
                    }
                    
                    if (sortOrder === 'asc') {
                        return aValue > bValue ? 1 : -1;
                    } else {
                        return aValue < bValue ? 1 : -1;
                    }
                });

                setPlayers(playersData);
            }
        } catch (error) {
            console.error('Error loading players:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadPlayerDetails = async (playerId) => {
        try {
            const response = await axios.get(`/api/users/${playerId}/player-stats`, {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                setPlayerDetails(response.data.data);
            }
        } catch (error) {
            console.error('Error loading player details:', error);
        }
    };

    const filteredPlayers = players.filter(player =>
        player.name.toLowerCase().includes(searchTerm.toLowerCase())
    );

    const getPerformanceColor = (value, type) => {
        if (type === 'win_rate') {
            if (value >= 0.7) return 'text-green-600';
            if (value >= 0.5) return 'text-yellow-600';
            return 'text-red-600';
        }
        if (type === 'rating') {
            if (value >= 1500) return 'text-green-600';
            if (value >= 1200) return 'text-yellow-600';
            return 'text-red-600';
        }
        return 'text-gray-600';
    };

    const getPerformanceIcon = (value, type) => {
        if (type === 'win_rate') {
            if (value >= 0.6) return <HiTrendingUp className="w-4 h-4 text-green-600" />;
            if (value >= 0.4) return <HiStar className="w-4 h-4 text-yellow-600" />;
            return <HiTrendingDown className="w-4 h-4 text-red-600" />;
        }
        return null;
    };

    const PlayerCard = ({ player, index }) => (
        <div 
            className={`bg-white rounded-xl shadow-sm border border-gray-100 p-4 cursor-pointer transition-colors ${
                selectedPlayer?.id === player.id ? 'ring-2 ring-primary' : 'hover:shadow-md'
            }`}
            onClick={() => {
                setSelectedPlayer(player);
                loadPlayerDetails(player.id);
            }}
        >
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <div className="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                        <span className="text-primary font-semibold text-sm">
                            #{index + 1}
                        </span>
                    </div>
                    <div>
                        <h3 className="font-medium text-gray-900">{player.name}</h3>
                        <p className="text-sm text-gray-600">
                            {player.events_participated} events • Joined: {new Date(player.joined_at).toLocaleDateString()}
                        </p>
                    </div>
                </div>
                <div className="text-right">
                    <div className="flex items-center space-x-2">
                        <div className={`text-lg font-semibold ${getPerformanceColor(player.rating, 'rating')}`}>
                            {player.rating}
                        </div>
                        {getPerformanceIcon(player.win_rate, 'win_rate')}
                    </div>
                    <div className="text-sm text-gray-500">
                        Win Rate: {Math.round(player.win_rate * 100)}%
                    </div>
                </div>
            </div>

            {/* Performance Indicators */}
            <div className="mt-3 grid grid-cols-3 gap-3">
                <div className="text-center p-2 bg-blue-50 rounded-lg">
                    <div className="text-sm font-semibold text-blue-600">
                        {player.events_participated}
                    </div>
                    <div className="text-xs text-blue-700">Events</div>
                </div>
                <div className="text-center p-2 bg-green-50 rounded-lg">
                    <div className={`text-sm font-semibold ${getPerformanceColor(player.win_rate, 'win_rate')}`}>
                        {Math.round(player.win_rate * 100)}%
                    </div>
                    <div className="text-xs text-green-700">Win Rate</div>
                </div>
                <div className="text-center p-2 bg-purple-50 rounded-lg">
                    <div className={`text-sm font-semibold ${getPerformanceColor(player.rating, 'rating')}`}>
                        {player.rating}
                    </div>
                    <div className="text-xs text-purple-700">MMR</div>
                </div>
            </div>

            {/* Status */}
            <div className="mt-3 flex items-center justify-between">
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                    player.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'
                }`}>
                    {player.status === 'active' ? 'Active' : 'Inactive'}
                </span>
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                    player.level === 'advanced' ? 'bg-red-100 text-red-700' :
                    player.level === 'intermediate' ? 'bg-yellow-100 text-yellow-700' :
                    'bg-green-100 text-green-700'
                }`}>
                    {player.level || 'Beginner'}
                </span>
            </div>
        </div>
    );

    const PlayerDetailModal = () => {
        if (!selectedPlayer || !playerDetails) return null;

        return (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div className="p-6">
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-xl font-bold text-gray-900">
                                Player Performance Detail
                            </h2>
                            <button
                                onClick={() => {
                                    setSelectedPlayer(null);
                                    setPlayerDetails(null);
                                }}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Player Info */}
                        <div className="bg-gray-50 rounded-xl p-6 mb-6">
                            <div className="flex items-center space-x-4">
                                <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center">
                                    <span className="text-primary font-bold text-xl">
                                        {selectedPlayer.name.charAt(0)}
                                    </span>
                                </div>
                                <div>
                                    <h3 className="text-xl font-bold text-gray-900">
                                        {selectedPlayer.name}
                                    </h3>
                                    <p className="text-gray-600">
                                        Member since {new Date(selectedPlayer.joined_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Performance Metrics */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div className="bg-blue-50 rounded-xl p-4 text-center">
                                <div className="text-2xl font-bold text-blue-600">
                                    {playerDetails.stats?.total_ratings || 0}
                                </div>
                                <div className="text-sm text-blue-700">Total Ratings</div>
                            </div>
                            <div className="bg-green-50 rounded-xl p-4 text-center">
                                <div className="text-2xl font-bold text-green-600">
                                    {playerDetails.stats?.average_skill || 0}
                                </div>
                                <div className="text-sm text-green-700">Avg Skill</div>
                            </div>
                            <div className="bg-yellow-50 rounded-xl p-4 text-center">
                                <div className="text-2xl font-bold text-yellow-600">
                                    {playerDetails.stats?.average_sportsmanship || 0}
                                </div>
                                <div className="text-sm text-yellow-700">Sportsmanship</div>
                            </div>
                            <div className="bg-purple-50 rounded-xl p-4 text-center">
                                <div className="text-2xl font-bold text-purple-600">
                                    {playerDetails.stats?.average_punctuality || 0}
                                </div>
                                <div className="text-sm text-purple-700">Punctuality</div>
                            </div>
                        </div>

                        {/* Overall Rating */}
                        <div className="bg-white border border-gray-200 rounded-xl p-6 mb-6">
                            <h4 className="font-semibold text-gray-900 mb-4">Overall Rating</h4>
                            <div className="flex items-center space-x-4">
                                <div className="text-3xl font-bold text-primary">
                                    {playerDetails.stats?.overall_average || 0}
                                </div>
                                <div className="flex-1">
                                    <div className="flex items-center space-x-2 mb-1">
                                        <span className="text-sm text-gray-600">Overall Performance</span>
                                        <div className="flex">
                                            {[...Array(5)].map((_, i) => (
                                                <HiStar
                                                    key={i}
                                                    className={`w-4 h-4 ${
                                                        i < Math.floor((playerDetails.stats?.overall_average || 0) / 2)
                                                            ? 'text-yellow-400'
                                                            : 'text-gray-300'
                                                    }`}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className="bg-primary h-2 rounded-full"
                                            style={{
                                                width: `${Math.min((playerDetails.stats?.overall_average || 0) / 10 * 100, 100)}%`
                                            }}
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Recent Activity */}
                        <div className="bg-white border border-gray-200 rounded-xl p-6">
                            <h4 className="font-semibold text-gray-900 mb-4">Performance Summary</h4>
                            <div className="space-y-3">
                                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span className="text-gray-700">Events Participated</span>
                                    <span className="font-semibold text-gray-900">
                                        {selectedPlayer.events_participated}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span className="text-gray-700">Current MMR</span>
                                    <span className={`font-semibold ${getPerformanceColor(selectedPlayer.rating, 'rating')}`}>
                                        {selectedPlayer.rating}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span className="text-gray-700">Win Rate</span>
                                    <span className={`font-semibold ${getPerformanceColor(selectedPlayer.win_rate, 'win_rate')}`}>
                                        {Math.round(selectedPlayer.win_rate * 100)}%
                                    </span>
                                </div>
                                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span className="text-gray-700">Player Level</span>
                                    <span className="font-semibold text-gray-900">
                                        {selectedPlayer.level || 'Beginner'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <div className="p-4 space-y-4">
            {/* Header */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div className="flex items-center justify-between mb-4">
                    <h1 className="text-lg font-semibold text-gray-900">Player Performance Analytics</h1>
                    <button
                        onClick={loadPlayers}
                        className="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                    >
                        <HiRefresh className="w-5 h-5" />
                    </button>
                </div>

                {/* Filters */}
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="flex-1">
                        <div className="relative">
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                placeholder="Cari player..."
                                className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            />
                            <HiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                        </div>
                    </div>

                    <select
                        value={selectedCommunity}
                        onChange={(e) => setSelectedCommunity(e.target.value)}
                        className="px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                        {communities.map(community => (
                            <option key={community.id} value={community.id}>
                                {community.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value)}
                        className="px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                        <option value="rating">Sort by MMR</option>
                        <option value="win_rate">Sort by Win Rate</option>
                        <option value="events_participated">Sort by Events</option>
                        <option value="name">Sort by Name</option>
                        <option value="joined_at">Sort by Join Date</option>
                    </select>

                    <select
                        value={sortOrder}
                        onChange={(e) => setSortOrder(e.target.value)}
                        className="px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                        <option value="desc">High to Low</option>
                        <option value="asc">Low to High</option>
                    </select>
                </div>
            </div>

            {/* Player List */}
            <div>
                {loading ? (
                    <div className="flex justify-center items-center h-64">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                ) : filteredPlayers.length === 0 ? (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <div className="text-center py-8">
                            <div className="text-6xl mb-4">👥</div>
                            <h3 className="font-semibold text-gray-900 mb-2">Tidak ada player ditemukan</h3>
                            <p className="text-gray-600 text-sm">
                                {searchTerm ? 'Coba ubah kata kunci pencarian' : 'Belum ada player di komunitas ini'}
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {filteredPlayers.map((player, index) => (
                            <PlayerCard key={player.id} player={player} index={index} />
                        ))}
                    </div>
                )}
            </div>

            <PlayerDetailModal />
        </div>
    );
};

export default PlayerPerformanceAnalytics;