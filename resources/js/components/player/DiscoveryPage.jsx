import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { getCurrentPosition } from '../../utils/locationUtils';
import DistanceDisplay from '../common/DistanceDisplay';
import LocationPicker from '../common/LocationPicker';

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
        date_range: 'all', // today, this_week, this_month, all
        event_type: '',
        date_from: '',
        date_to: ''
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [currentLocation, setCurrentLocation] = useState(null);
    const [searchRadius, setSearchRadius] = useState(10); // Default 10km

    // Load initial data
    useEffect(() => {
        loadSports();
        if (activeTab === 'events') {
            loadEvents();
        } else {
            loadCommunities();
        }
    }, [activeTab, filters]);

    // Get current location and load data
    useEffect(() => {
        const initializeLocation = async () => {
            try {
                const position = await getCurrentPosition();
                setCurrentLocation(position);
                await fetchData(position);
            } catch (error) {
                console.error('Error getting location:', error);
                setError('Please enable location services to see nearby events and communities');
                setIsLoading(false);
            }
        };

        initializeLocation();
    }, []);

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

    // Fetch data based on location
    const fetchData = async (location) => {
        if (!location) return;

        setIsLoading(true);
        try {
            const [eventsResponse, communitiesResponse] = await Promise.all([
                axios.get('/events/nearby', {
                    params: {
                        latitude: location.latitude,
                        longitude: location.longitude,
                        radius_km: searchRadius,
                        ...filters
                    }
                }),
                axios.get('/communities/nearby', {
                    params: {
                        latitude: location.latitude,
                        longitude: location.longitude,
                        radius_km: searchRadius,
                        sport_id: filters.sport_id
                    }
                })
            ]);

            setEvents(eventsResponse.data.data.events);
            setCommunities(communitiesResponse.data.data.communities);
            setError(null);
        } catch (error) {
            console.error('Error fetching data:', error);
            setError('Failed to load nearby events and communities');
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
        const newFilters = { ...filters, [key]: value };
        setFilters(newFilters);
        if (currentLocation) {
            fetchData(currentLocation);
        }
    };

    const clearFilters = () => {
        setFilters({
            sport_id: '',
            skill_level: '',
            city: '',
            date_range: 'all',
            event_type: '',
            date_from: '',
            date_to: ''
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
        if (!timeString) return 'TBD';
        
        try {
            // Handle full datetime format
            if (timeString.includes('T') || timeString.includes(' ')) {
                const date = new Date(timeString);
                return date.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // Handle time-only format (HH:MM:SS or HH:MM)
            const timeParts = timeString.split(':');
            if (timeParts.length >= 2) {
                const hours = timeParts[0].padStart(2, '0');
                const minutes = timeParts[1].padStart(2, '0');
                return `${hours}:${minutes}`;
            }
            
            // Fallback: try to parse as date
        return new Date(`2000-01-01T${timeString}`).toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });
        } catch (error) {
            console.error('Error formatting time:', timeString, error);
            return 'TBD';
        }
    };

    const EventCard = ({ event }) => {
        // Use backend calculated participant counts
        const participantsCount = event.participants_count || event.confirmed_participants_count || 0;
        
        return (
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
                {/* Header */}
                <div className="mb-4">
                    <h3 className="font-bold text-lg text-gray-900 mb-2">{event.title}</h3>
                    <p className="text-gray-600 text-sm leading-relaxed mb-3 line-clamp-2">{event.description}</p>
                </div>
                
                {/* Event Info Grid */}
                <div className="grid grid-cols-2 gap-3 mb-4">
                    <div className="flex items-center text-sm text-gray-600">
                        <span className="mr-2 text-base">🏸</span>
                        <span className="font-medium">{event.sport?.name || 'Sport'}</span>
                    </div>
                    <div className="flex items-center text-sm text-gray-600">
                        <span className="mr-2 text-base">👥</span>
                        <span>{participantsCount}/{event.max_participants} peserta</span>
                    </div>
                    <div className="flex items-center text-sm text-gray-600">
                        <span className="mr-2 text-base">📅</span>
                        <span>{event.event_date ? formatDate(event.event_date) : 'Tanggal TBD'}</span>
                    </div>
                    <div className="flex items-center text-sm text-gray-600">
                        <span className="mr-2 text-base">⏰</span>
                        <span>{event.start_time ? formatTime(event.start_time) : 'Waktu TBD'}</span>
                    </div>
                </div>

                {/* Location */}
                <div className="flex items-center text-sm text-gray-600 mb-4">
                    <span className="mr-2 text-base">📍</span>
                    <span className="flex-1 truncate">{event.location_name || 'Lokasi TBD'}</span>
                </div>

                {/* Price and Level */}
                <div className="flex items-center justify-between mb-4">
                    <div className="text-lg font-bold text-primary">
                        {event.entry_fee ? `Rp ${parseInt(event.entry_fee).toLocaleString()}` : 'GRATIS'}
                    </div>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                        event.skill_level_required === 'pemula' ? 'bg-green-100 text-green-700' :
                        event.skill_level_required === 'menengah' ? 'bg-blue-100 text-blue-700' :
                        event.skill_level_required === 'mahir' ? 'bg-orange-100 text-orange-700' :
                        'bg-gray-100 text-gray-700'
                    }`}>
                        {event.skill_level_required || 'Semua Level'}
                    </span>
            </div>

                {/* Action Button */}
            <button 
                onClick={() => onNavigate('eventDetail', { eventId: event.id })}
                    className="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-primary-dark transition-colors"
            >
                    Lihat Detail Event
            </button>
        </div>
    );
    };

    const CommunityCard = ({ community }) => (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
            {/* Header */}
            <div className="mb-4">
                <div className="flex items-start justify-between mb-2">
                    <h3 className="font-bold text-lg text-gray-900 flex-1">{community.name}</h3>
                    {community.has_icon && community.icon_url && (
                        <img 
                            src={community.icon_url} 
                            alt={community.name}
                            className="w-10 h-10 rounded-lg object-cover ml-3"
                        />
                    )}
                </div>
                <p className="text-gray-600 text-sm leading-relaxed mb-3 line-clamp-2">{community.description}</p>
                    </div>

            {/* Community Info Grid */}
            <div className="grid grid-cols-2 gap-3 mb-4">
                <div className="flex items-center text-sm text-gray-600">
                    <span className="mr-2 text-base">🏸</span>
                    <span className="font-medium">{community.sport?.name || 'Sport'}</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                    <span className="mr-2 text-base">👥</span>
                    <span>{community.member_count || 0} member</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                    <span className="mr-2 text-base">⭐</span>
                    <span>Fokus: {community.skill_level_focus || 'Mixed'}</span>
                    </div>
                <div className="flex items-center text-sm text-gray-600">
                    <span className="mr-2 text-base">🎯</span>
                    <span>Rating: {community.average_skill_rating ? parseFloat(community.average_skill_rating).toFixed(1) : 'N/A'}</span>
                </div>
            </div>

            {/* Location */}
            <div className="flex items-center text-sm text-gray-600 mb-4">
                <span className="mr-2 text-base">📍</span>
                <span className="flex-1 truncate">{community.venue_city || community.city}</span>
                </div>

            {/* Price and Type */}
            <div className="flex items-center justify-between mb-4">
                <div className="text-lg font-bold text-primary">
                    {community.membership_fee ? `Rp ${community.membership_fee.toLocaleString()}/bulan` : 'GRATIS'}
                    </div>
                <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                        community.community_type === 'public' ? 'bg-green-100 text-green-700' :
                        community.community_type === 'private' ? 'bg-blue-100 text-blue-700' :
                        'bg-orange-100 text-orange-700'
                    }`}>
                        {community.community_type === 'public' ? 'Publik' :
                         community.community_type === 'private' ? 'Privat' : 'Undangan'}
                    </span>
            </div>

            {/* Action Button */}
            <button 
                onClick={() => onNavigate('communityDetail', { communityId: community.id })}
                className="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-primary-dark transition-colors"
            >
                Lihat Detail Komunitas
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