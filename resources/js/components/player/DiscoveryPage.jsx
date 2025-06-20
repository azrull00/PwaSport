import React, { useState, useEffect } from 'react';

const DiscoveryPage = ({ userToken, onNavigate }) => {
    const [activeTab, setActiveTab] = useState('events'); // 'events' atau 'communities'
    const [searchQuery, setSearchQuery] = useState('');
    const [events, setEvents] = useState([]);
    const [communities, setCommunities] = useState([]);
    const [sports, setSports] = useState([]);
    const [filters, setFilters] = useState({
        sport_id: '',
        skill_level: '',
        city: '',
        date_range: 'all' // today, this_week, this_month, all
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');

    // Load initial data
    useEffect(() => {
        loadSports();
        if (activeTab === 'events') {
            loadEvents();
        } else {
            loadCommunities();
        }
    }, [activeTab, filters]);

    const loadSports = async () => {
        try {
            const response = await fetch('/api/sports', {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (data.status === 'success') {
                setSports(data.data.sports);
            }
        } catch (error) {
            console.error('Error loading sports:', error);
        }
    };

    const loadEvents = async () => {
        setIsLoading(true);
        setError('');
        
        try {
            const params = new URLSearchParams({
                per_page: '20',
                available_only: 'true'
            });

            // Add filters
            if (filters.sport_id) params.append('sport_id', filters.sport_id);
            if (filters.skill_level) params.append('skill_level', filters.skill_level);
            if (filters.city) params.append('city', filters.city);
            if (searchQuery) params.append('search', searchQuery);

            const response = await fetch(`/api/events?${params.toString()}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            console.log('Events response:', data);
            console.log('DISCOVERY - data.data:', data.data);
            console.log('DISCOVERY - data.data.events:', data.data.events);
            console.log('DISCOVERY - data.data.events.data:', data.data ? data.data.events ? data.data.events.data : 'events not found' : 'data not found');
            
            if (data.status === 'success') {
                // Parse paginated events response
                let events = [];
                if (data.data && data.data.events && data.data.events.data) {
                    events = data.data.events.data;
                    console.log('DISCOVERY SUCCESS: Found events array with length:', events.length);
                } else {
                    console.log('DISCOVERY FAILED: Events parsing condition failed');
                    console.log('- data.data exists:', !!data.data);
                    console.log('- data.data.events exists:', !!(data.data && data.data.events));
                    console.log('- data.data.events.data exists:', !!(data.data && data.data.events && data.data.events.data));
                }
                console.log('DISCOVERY - Final parsed events:', events);
                setEvents(events);
            } else {
                setError('Gagal memuat event');
            }
        } catch (error) {
            console.error('Error loading events:', error);
            setError('Terjadi kesalahan saat memuat event');
        } finally {
            setIsLoading(false);
        }
    };

    const loadCommunities = async () => {
        setIsLoading(true);
        setError('');
        
        try {
            const params = new URLSearchParams({
                per_page: '20'
            });

            // Add filters
            if (filters.sport_id) params.append('sport_id', filters.sport_id);
            if (filters.skill_level) params.append('skill_level_focus', filters.skill_level);
            if (filters.city) params.append('city', filters.city);
            if (searchQuery) params.append('search', searchQuery);

            const response = await fetch(`/api/communities?${params.toString()}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            console.log('Communities response:', data);
            
            if (data.status === 'success') {
                // Parse paginated communities response
                let communities = [];
                if (data.data && data.data.communities && data.data.communities.data) {
                    communities = data.data.communities.data;
                }
                console.log('Parsed communities in Discovery:', communities);
                setCommunities(communities);
            } else {
                setError('Gagal memuat komunitas');
            }
        } catch (error) {
            console.error('Error loading communities:', error);
            setError('Terjadi kesalahan saat memuat komunitas');
        } finally {
            setIsLoading(false);
        }
    };

    const handleSearch = () => {
        if (activeTab === 'events') {
            loadEvents();
        } else {
            loadCommunities();
        }
    };

    const handleFilterChange = (key, value) => {
        setFilters(prev => ({
            ...prev,
            [key]: value
        }));
    };

    const clearFilters = () => {
        setFilters({
            sport_id: '',
            skill_level: '',
            city: '',
            date_range: 'all'
        });
        setSearchQuery('');
    };

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    const formatTime = (timeString) => {
        return new Date(`2000-01-01T${timeString}`).toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const EventCard = ({ event }) => (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3">
            <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                    <h3 className="font-semibold text-gray-900 mb-1">{event.title}</h3>
                    <p className="text-sm text-gray-600 mb-2">{event.description}</p>
                    
                    <div className="flex items-center text-xs text-gray-500 space-x-4 mb-2">
                        <span className="flex items-center">
                            <span className="mr-1">🏸</span>
                            {event.sport?.name || 'Sport'}
                        </span>
                        <span className="flex items-center">
                            <span className="mr-1">📅</span>
                            {formatDate(event.event_date)}
                        </span>
                        <span className="flex items-center">
                            <span className="mr-1">⏰</span>
                            {formatTime(event.start_time)}
                        </span>
                    </div>

                    <div className="flex items-center text-xs text-gray-500 space-x-4">
                        <span className="flex items-center">
                            <span className="mr-1">📍</span>
                            {event.location_name}
                        </span>
                        <span className="flex items-center">
                            <span className="mr-1">👥</span>
                            {event.participants_count || 0}/{event.max_participants}
                        </span>
                    </div>
                </div>

                <div className="text-right">
                    <div className="text-sm font-semibold text-primary mb-1">
                        {event.entry_fee ? `Rp ${event.entry_fee.toLocaleString()}` : 'Gratis'}
                    </div>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                        event.skill_level_required === 'pemula' ? 'bg-green-100 text-green-700' :
                        event.skill_level_required === 'menengah' ? 'bg-blue-100 text-blue-700' :
                        event.skill_level_required === 'mahir' ? 'bg-orange-100 text-orange-700' :
                        'bg-gray-100 text-gray-700'
                    }`}>
                        {event.skill_level_required || 'Semua Level'}
                    </span>
                </div>
            </div>

            <button 
                onClick={() => onNavigate('eventDetail', { eventId: event.id })}
                className="w-full bg-primary text-white py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors"
            >
                Lihat Detail
            </button>
        </div>
    );

    const CommunityCard = ({ community }) => (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3">
            <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                    <h3 className="font-semibold text-gray-900 mb-1">{community.name}</h3>
                    <p className="text-sm text-gray-600 mb-2">{community.description}</p>
                    
                    <div className="flex items-center text-xs text-gray-500 space-x-4 mb-2">
                        <span className="flex items-center">
                            <span className="mr-1">🏸</span>
                            {community.sport?.name || 'Sport'}
                        </span>
                        <span className="flex items-center">
                            <span className="mr-1">📍</span>
                            {community.venue_city || community.city}
                        </span>
                        <span className="flex items-center">
                            <span className="mr-1">👥</span>
                            {community.member_count || 0} member
                        </span>
                    </div>

                    <div className="flex items-center text-xs text-gray-500">
                        <span className="flex items-center">
                            <span className="mr-1">⭐</span>
                            Fokus: {community.skill_level_focus || 'Mixed'}
                        </span>
                    </div>
                </div>

                <div className="text-right">
                    <div className="text-sm font-semibold text-primary mb-1">
                        {community.membership_fee ? `Rp ${community.membership_fee.toLocaleString()}/bulan` : 'Gratis'}
                    </div>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                        community.community_type === 'public' ? 'bg-green-100 text-green-700' :
                        community.community_type === 'private' ? 'bg-blue-100 text-blue-700' :
                        'bg-orange-100 text-orange-700'
                    }`}>
                        {community.community_type === 'public' ? 'Publik' :
                         community.community_type === 'private' ? 'Privat' : 'Undangan'}
                    </span>
                </div>
            </div>

            <button 
                onClick={() => onNavigate('communityDetail', { communityId: community.id })}
                className="w-full bg-primary text-white py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors"
            >
                Lihat Detail
            </button>
        </div>
    );

    return (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <h1 className="text-lg font-semibold text-gray-900">Jelajah</h1>
            </div>

            {/* Search Bar */}
            <div className="p-4">
                <div className="relative">
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder={`Cari ${activeTab === 'events' ? 'event' : 'komunitas'}...`}
                        className="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent"
                        onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                    />
                    <span className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">🔍</span>
                    <button 
                        onClick={handleSearch}
                        className="absolute right-2 top-1/2 transform -translate-y-1/2 bg-primary text-white px-4 py-1.5 rounded-lg text-sm font-medium"
                    >
                        Cari
                    </button>
                </div>
            </div>

            {/* Tabs */}
            <div className="px-4 mb-4">
                <div className="flex bg-gray-100 rounded-xl p-1">
                    <button
                        onClick={() => setActiveTab('events')}
                        className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors ${
                            activeTab === 'events'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Event
                    </button>
                    <button
                        onClick={() => setActiveTab('communities')}
                        className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors ${
                            activeTab === 'communities'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Komunitas
                    </button>
                </div>
            </div>

            {/* Filters */}
            <div className="px-4 mb-4">
                <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="font-medium text-gray-900">Filter</h3>
                        <button 
                            onClick={clearFilters}
                            className="text-primary text-sm font-medium"
                        >
                            Reset
                        </button>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        {/* Sport Filter */}
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Olahraga</label>
                            <select
                                value={filters.sport_id}
                                onChange={(e) => handleFilterChange('sport_id', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                            >
                                <option value="">Semua</option>
                                {sports.map((sport) => (
                                    <option key={sport.id} value={sport.id}>
                                        {sport.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Skill Level Filter */}
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Level</label>
                            <select
                                value={filters.skill_level}
                                onChange={(e) => handleFilterChange('skill_level', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                            >
                                <option value="">Semua Level</option>
                                <option value="pemula">Pemula</option>
                                <option value="menengah">Menengah</option>
                                <option value="mahir">Mahir</option>
                                <option value="ahli">Ahli</option>
                                <option value="profesional">Profesional</option>
                                <option value="mixed">Campuran</option>
                            </select>
                        </div>
                    </div>

                    <div className="mt-3">
                        <label className="block text-xs font-medium text-gray-700 mb-1">Kota</label>
                        <input
                            type="text"
                            value={filters.city}
                            onChange={(e) => handleFilterChange('city', e.target.value)}
                            placeholder="Masukkan nama kota"
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                        />
                    </div>

                    {activeTab === 'events' && (
                        <div className="mt-3">
                            <label className="block text-xs font-medium text-gray-700 mb-1">Waktu</label>
                            <select
                                value={filters.date_range}
                                onChange={(e) => handleFilterChange('date_range', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                            >
                                <option value="all">Semua Waktu</option>
                                <option value="today">Hari Ini</option>
                                <option value="this_week">Minggu Ini</option>
                                <option value="this_month">Bulan Ini</option>
                            </select>
                        </div>
                    )}
                </div>
            </div>

            {/* Content */}
            <div className="px-4 pb-20">
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                        <p className="text-red-600 text-sm">{error}</p>
                    </div>
                )}

                {isLoading ? (
                    <div className="flex justify-center py-8">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                ) : (
                    <div>
                        {activeTab === 'events' ? (
                            events.length > 0 ? (
                                events.map((event) => (
                                    <EventCard key={event.id} event={event} />
                                ))
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-6xl mb-4">🔍</div>
                                    <h3 className="font-semibold text-gray-900 mb-2">Tidak ada event ditemukan</h3>
                                    <p className="text-gray-600 text-sm">Coba ubah filter pencarian Anda</p>
                                </div>
                            )
                        ) : (
                            communities.length > 0 ? (
                                communities.map((community) => (
                                    <CommunityCard key={community.id} community={community} />
                                ))
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-6xl mb-4">🔍</div>
                                    <h3 className="font-semibold text-gray-900 mb-2">Tidak ada komunitas ditemukan</h3>
                                    <p className="text-gray-600 text-sm">Coba ubah filter pencarian Anda</p>
                                </div>
                            )
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};

export default DiscoveryPage; 