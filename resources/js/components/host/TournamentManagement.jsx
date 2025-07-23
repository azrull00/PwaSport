import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { 
    HiAcademicCap as HiTrophy, 
    HiUsers, 
    HiCalendar, 
    HiClock,
    HiPlay,
    HiStop,
    HiRefresh,
    HiPlus
} from 'react-icons/hi';

const TournamentManagement = ({ userToken }) => {
    const [activeTab, setActiveTab] = useState('tournaments');
    const [tournaments, setTournaments] = useState([]);
    const [selectedTournament, setSelectedTournament] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showCreateModal, setShowCreateModal] = useState(false);

    useEffect(() => {
        loadTournaments();
    }, []);

    const loadTournaments = async () => {
        setLoading(true);
        try {
            // Load tournament events (events with tournament type)
            const response = await axios.get('/events?event_type=tournament&host_only=true', {
                headers: { 'Authorization': `Bearer ${userToken}` }
            });

            if (response.data.status === 'success') {
                const events = response.data.data.events?.data || [];
                setTournaments(events);
                if (events.length > 0) {
                    setSelectedTournament(events[0]);
                }
            }
        } catch (error) {
            console.error('Error loading tournaments:', error);
        } finally {
            setLoading(false);
        }
    };

    const TournamentCard = ({ tournament }) => {
        const getStatusColor = (status) => {
            switch (status) {
                case 'active': return 'bg-green-100 text-green-700';
                case 'completed': return 'bg-blue-100 text-blue-700';
                case 'cancelled': return 'bg-red-100 text-red-700';
                default: return 'bg-gray-100 text-gray-700';
            }
        };

        return (
            <div 
                className={`bg-white rounded-xl shadow-sm border border-gray-100 p-6 cursor-pointer transition-colors ${
                    selectedTournament?.id === tournament.id ? 'ring-2 ring-primary' : 'hover:shadow-md'
                }`}
                onClick={() => setSelectedTournament(tournament)}
            >
                {/* Header */}
                <div className="flex items-start justify-between mb-4">
                    <div className="flex items-center space-x-3">
                        <div className="p-3 bg-yellow-100 rounded-lg">
                            <HiTrophy className="w-6 h-6 text-yellow-600" />
                        </div>
                        <div>
                            <h3 className="font-bold text-lg text-gray-900">{tournament.title}</h3>
                            <p className="text-sm text-gray-600">{tournament.sport?.name}</p>
                        </div>
                    </div>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(tournament.status)}`}>
                        {tournament.status === 'active' ? 'Aktif' :
                         tournament.status === 'completed' ? 'Selesai' :
                         tournament.status === 'cancelled' ? 'Dibatalkan' : tournament.status}
                    </span>
                </div>

                {/* Tournament Info */}
                <div className="grid grid-cols-2 gap-3 mb-4">
                    <div className="flex items-center text-sm text-gray-600">
                        <HiUsers className="w-4 h-4 mr-2" />
                        <span>{tournament.participants_count || 0}/{tournament.max_participants} peserta</span>
                    </div>
                    <div className="flex items-center text-sm text-gray-600">
                        <HiCalendar className="w-4 h-4 mr-2" />
                        <span>{new Date(tournament.event_date).toLocaleDateString()}</span>
                    </div>
                </div>

                {/* Prize Info */}
                {tournament.prizes && (
                    <div className="flex items-center text-sm text-gray-600 mb-4">
                        <span className="mr-2">🏆</span>
                        <span>Prize Pool: {tournament.prizes}</span>
                    </div>
                )}

                {/* Entry Fee */}
                <div className="flex items-center justify-between">
                    <div className="text-lg font-bold text-primary">
                        {tournament.entry_fee ? `Rp ${parseInt(tournament.entry_fee).toLocaleString()}` : 'GRATIS'}
                    </div>
                    <div className="text-sm text-gray-500">
                        ID: {tournament.id}
                    </div>
                </div>
            </div>
        );
    };

    const TournamentDetail = ({ tournament }) => {
        const [matches, setMatches] = useState([]);
        const [participants, setParticipants] = useState([]);
        const [bracket, setBracket] = useState([]);
        const [loadingDetail, setLoadingDetail] = useState(true);

        useEffect(() => {
            if (tournament) {
                loadTournamentDetail();
            }
        }, [tournament.id]);

        const loadTournamentDetail = async () => {
            setLoadingDetail(true);
            try {
                const [matchesRes, participantsRes] = await Promise.all([
                    axios.get(`/matches?event_id=${tournament.id}`, {
                        headers: { 'Authorization': `Bearer ${userToken}` }
                    }),
                    axios.get(`/events/${tournament.id}/participants`, {
                        headers: { 'Authorization': `Bearer ${userToken}` }
                    })
                ]);

                if (matchesRes.data.status === 'success') {
                    setMatches(matchesRes.data.data.matches || []);
                }

                if (participantsRes.data.status === 'success') {
                    setParticipants(participantsRes.data.data.participants || []);
                }

                // Generate simple bracket from matches
                generateBracket(matchesRes.data.data.matches || []);

            } catch (error) {
                console.error('Error loading tournament detail:', error);
            } finally {
                setLoadingDetail(false);
            }
        };

        const generateBracket = (matches) => {
            // Simple bracket generation - group matches by rounds
            const rounds = {};
            matches.forEach(match => {
                const round = match.round || 1;
                if (!rounds[round]) rounds[round] = [];
                rounds[round].push(match);
            });

            const bracketData = Object.keys(rounds)
                .sort((a, b) => parseInt(a) - parseInt(b))
                .map(round => ({
                    round: parseInt(round),
                    matches: rounds[round]
                }));

            setBracket(bracketData);
        };

        const generateMatches = async () => {
            try {
                const response = await axios.post(`/matchmaking/${tournament.id}/generate`, {
                    match_type: 'singles',
                    tournament_mode: true
                }, {
                    headers: { 'Authorization': `Bearer ${userToken}` }
                });

                if (response.data.status === 'success') {
                    loadTournamentDetail();
                }
            } catch (error) {
                console.error('Error generating matches:', error);
            }
        };

        if (loadingDetail) {
            return (
                <div className="flex justify-center items-center h-64">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            );
        }

        return (
            <div className="space-y-6">
                {/* Tournament Info */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-xl font-bold text-gray-900">{tournament.title}</h2>
                        <div className="flex space-x-2">
                            <button
                                onClick={generateMatches}
                                className="flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors text-sm"
                            >
                                <HiPlay className="w-4 h-4 mr-2" />
                                Generate Matches
                            </button>
                            <button
                                onClick={loadTournamentDetail}
                                className="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100"
                            >
                                <HiRefresh className="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="text-center p-4 bg-blue-50 rounded-lg">
                            <div className="text-2xl font-bold text-blue-600">
                                {participants.length}
                            </div>
                            <div className="text-sm text-blue-700">Total Peserta</div>
                        </div>
                        <div className="text-center p-4 bg-green-50 rounded-lg">
                            <div className="text-2xl font-bold text-green-600">
                                {matches.filter(m => m.match_status === 'completed').length}
                            </div>
                            <div className="text-sm text-green-700">Match Selesai</div>
                        </div>
                        <div className="text-center p-4 bg-yellow-50 rounded-lg">
                            <div className="text-2xl font-bold text-yellow-600">
                                {matches.filter(m => m.match_status === 'ongoing').length}
                            </div>
                            <div className="text-sm text-yellow-700">Match Berlangsung</div>
                        </div>
                        <div className="text-center p-4 bg-purple-50 rounded-lg">
                            <div className="text-2xl font-bold text-purple-600">
                                {bracket.length}
                            </div>
                            <div className="text-sm text-purple-700">Rounds</div>
                        </div>
                    </div>
                </div>

                {/* Tournament Bracket */}
                {bracket.length > 0 && (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">Tournament Bracket</h3>
                        <div className="space-y-6">
                            {bracket.map(round => (
                                <div key={round.round}>
                                    <h4 className="font-medium text-gray-700 mb-3">
                                        Round {round.round}
                                    </h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {round.matches.map(match => (
                                            <div key={match.id} className="bg-gray-50 rounded-lg p-4">
                                                <div className="flex items-center justify-between mb-2">
                                                    <div className="flex items-center space-x-2">
                                                        <span className="text-sm font-medium text-gray-600">
                                                            Match #{match.id}
                                                        </span>
                                                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                            match.match_status === 'completed' ? 'bg-green-100 text-green-700' :
                                                            match.match_status === 'ongoing' ? 'bg-yellow-100 text-yellow-700' :
                                                            'bg-gray-100 text-gray-700'
                                                        }`}>
                                                            {match.match_status}
                                                        </span>
                                                    </div>
                                                    {match.court_number && (
                                                        <span className="text-sm text-gray-500">
                                                            Court {match.court_number}
                                                        </span>
                                                    )}
                                                </div>
                                                
                                                <div className="space-y-2">
                                                    <div className={`flex items-center justify-between p-2 rounded ${
                                                        match.winner_id === match.player1_id ? 'bg-green-100' : 'bg-white'
                                                    }`}>
                                                        <span className="font-medium">
                                                            {match.player1?.name || 'TBD'}
                                                        </span>
                                                        <span className="font-bold">
                                                            {match.player1_score || '-'}
                                                        </span>
                                                    </div>
                                                    <div className={`flex items-center justify-between p-2 rounded ${
                                                        match.winner_id === match.player2_id ? 'bg-green-100' : 'bg-white'
                                                    }`}>
                                                        <span className="font-medium">
                                                            {match.player2?.name || 'TBD'}
                                                        </span>
                                                        <span className="font-bold">
                                                            {match.player2_score || '-'}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Participants List */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Peserta Tournament</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {participants.map((participant, index) => (
                            <div key={participant.id} className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                <div className="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                    <span className="text-primary font-semibold text-sm">
                                        #{index + 1}
                                    </span>
                                </div>
                                <div className="flex-1">
                                    <h4 className="font-medium text-gray-900">{participant.user.name}</h4>
                                    <p className="text-sm text-gray-600">
                                        Status: {participant.status} • 
                                        {participant.checked_in_at ? ' Checked In' : ' Not Checked In'}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        );
    };

    const CreateTournamentModal = () => {
        const [formData, setFormData] = useState({
            title: '',
            description: '',
            sport_id: '',
            event_date: '',
            start_time: '',
            max_participants: 16,
            entry_fee: 0,
            prizes: '',
            venue_id: ''
        });
        const [sports, setSports] = useState([]);
        const [venues, setVenues] = useState([]);

        useEffect(() => {
            loadSports();
            loadVenues();
        }, []);

        const loadSports = async () => {
            try {
                const response = await axios.get('/sports');
                if (response.data.status === 'success') {
                    setSports(response.data.data.sports);
                }
            } catch (error) {
                console.error('Error loading sports:', error);
            }
        };

        const loadVenues = async () => {
            try {
                const response = await axios.get('/host/venues', {
                    headers: { 'Authorization': `Bearer ${userToken}` }
                });
                if (response.data.status === 'success') {
                    setVenues(response.data.data || []);
                }
            } catch (error) {
                console.error('Error loading venues:', error);
            }
        };

        const handleSubmit = async (e) => {
            e.preventDefault();
            try {
                const response = await axios.post('/events', {
                    ...formData,
                    event_type: 'tournament',
                    skill_level_required: 'mixed'
                }, {
                    headers: { 'Authorization': `Bearer ${userToken}` }
                });

                if (response.data.status === 'success') {
                    setShowCreateModal(false);
                    loadTournaments();
                    setFormData({
                        title: '',
                        description: '',
                        sport_id: '',
                        event_date: '',
                        start_time: '',
                        max_participants: 16,
                        entry_fee: 0,
                        prizes: '',
                        venue_id: ''
                    });
                }
            } catch (error) {
                console.error('Error creating tournament:', error);
            }
        };

        if (!showCreateModal) return null;

        return (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div className="bg-white rounded-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                    <div className="p-6">
                        <h2 className="text-xl font-bold text-gray-900 mb-4">Buat Tournament Baru</h2>
                        
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Nama Tournament
                                </label>
                                <input
                                    type="text"
                                    value={formData.title}
                                    onChange={(e) => setFormData({...formData, title: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Olahraga
                                </label>
                                <select
                                    value={formData.sport_id}
                                    onChange={(e) => setFormData({...formData, sport_id: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    required
                                >
                                    <option value="">Pilih Olahraga</option>
                                    {sports.map(sport => (
                                        <option key={sport.id} value={sport.id}>
                                            {sport.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Venue
                                </label>
                                <select
                                    value={formData.venue_id}
                                    onChange={(e) => setFormData({...formData, venue_id: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                >
                                    <option value="">Pilih Venue</option>
                                    {venues.map(venue => (
                                        <option key={venue.id} value={venue.id}>
                                            {venue.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Tanggal
                                    </label>
                                    <input
                                        type="date"
                                        value={formData.event_date}
                                        onChange={(e) => setFormData({...formData, event_date: e.target.value})}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Waktu
                                    </label>
                                    <input
                                        type="time"
                                        value={formData.start_time}
                                        onChange={(e) => setFormData({...formData, start_time: e.target.value})}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Max Peserta
                                    </label>
                                    <select
                                        value={formData.max_participants}
                                        onChange={(e) => setFormData({...formData, max_participants: parseInt(e.target.value)})}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    >
                                        <option value={8}>8 Peserta</option>
                                        <option value={16}>16 Peserta</option>
                                        <option value={32}>32 Peserta</option>
                                        <option value={64}>64 Peserta</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Entry Fee (Rp)
                                    </label>
                                    <input
                                        type="number"
                                        value={formData.entry_fee}
                                        onChange={(e) => setFormData({...formData, entry_fee: parseInt(e.target.value)})}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                        min="0"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Prize Pool
                                </label>
                                <input
                                    type="text"
                                    value={formData.prizes}
                                    onChange={(e) => setFormData({...formData, prizes: e.target.value})}
                                    placeholder="e.g., Juara 1: Rp 1,000,000"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Deskripsi
                                </label>
                                <textarea
                                    value={formData.description}
                                    onChange={(e) => setFormData({...formData, description: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    rows="3"
                                />
                            </div>

                            <div className="flex space-x-3">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    className="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark"
                                >
                                    Buat Tournament
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        );
    };

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
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-semibold text-gray-900">Tournament Management</h1>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors text-sm"
                    >
                        <HiPlus className="w-4 h-4 mr-2" />
                        Buat Tournament
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {/* Tournament List */}
                <div className="lg:col-span-1">
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <h2 className="font-semibold text-gray-900 mb-4">Tournament List</h2>
                        {tournaments.length === 0 ? (
                            <div className="text-center py-8">
                                <div className="text-6xl mb-4">🏆</div>
                                <h3 className="font-semibold text-gray-900 mb-2">Belum ada tournament</h3>
                                <p className="text-gray-600 text-sm">Buat tournament pertama Anda</p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {tournaments.map(tournament => (
                                    <TournamentCard key={tournament.id} tournament={tournament} />
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Tournament Detail */}
                <div className="lg:col-span-2">
                    {selectedTournament ? (
                        <TournamentDetail tournament={selectedTournament} />
                    ) : (
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <div className="text-center py-8">
                                <div className="text-6xl mb-4">🏆</div>
                                <h3 className="font-semibold text-gray-900 mb-2">Pilih Tournament</h3>
                                <p className="text-gray-600 text-sm">Pilih tournament untuk melihat detail</p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <CreateTournamentModal />
        </div>
    );
};

export default TournamentManagement;