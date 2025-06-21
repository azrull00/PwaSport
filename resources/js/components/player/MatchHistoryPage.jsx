import React, { useState, useEffect } from 'react';

const MatchHistoryPage = ({ userToken, onNavigate, onBack }) => {
    const [matchData, setMatchData] = useState({
        matches: { data: [] },
        statistics: {}
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [filters, setFilters] = useState({
        sport_id: '',
        result: '',
        date_from: '',
        date_to: '',
        per_page: 10
    });

    useEffect(() => {
        fetchMatchHistory();
    }, [userToken]);

    const fetchMatchHistory = async (newFilters = {}) => {
        try {
            setLoading(true);
            const params = new URLSearchParams({
                ...filters,
                ...newFilters
            });

            // Remove empty values
            for (let key of params.keys()) {
                if (!params.get(key)) {
                    params.delete(key);
                }
            }

            const response = await fetch(`/api/users/my-match-history?${params}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.status === 'success') {
                    setMatchData(data.data);
                    setError('');
                }
            } else {
                setError('Gagal mengambil riwayat pertandingan');
            }
        } catch (error) {
            console.error('Error fetching match history:', error);
            setError('Terjadi kesalahan saat mengambil data');
        } finally {
            setLoading(false);
        }
    };

    const handleFilterChange = (key, value) => {
        const newFilters = { ...filters, [key]: value };
        setFilters(newFilters);
        fetchMatchHistory(newFilters);
    };

    const clearFilters = () => {
        const clearedFilters = {
            sport_id: '',
            result: '',
            date_from: '',
            date_to: '',
            per_page: 10
        };
        setFilters(clearedFilters);
        fetchMatchHistory(clearedFilters);
    };

    const formatDate = (dateTime) => {
        const date = new Date(dateTime);
        return new Intl.DateTimeFormat('id-ID', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(date);
    };

    const getResultColor = (result) => {
        switch (result) {
            case 'win': return 'text-green-600 bg-green-100';
            case 'loss': return 'text-red-600 bg-red-100';
            case 'draw': return 'text-gray-600 bg-gray-100';
            default: return 'text-gray-600 bg-gray-100';
        }
    };

    if (loading && (!matchData.matches || !matchData.matches.data || matchData.matches.data.length === 0)) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                    <p className="text-gray-600">Memuat riwayat pertandingan...</p>
                </div>
            </div>
        );
    }

    if (error && (!matchData.matches || !matchData.matches.data || matchData.matches.data.length === 0)) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg className="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 className="font-semibold text-gray-900 mb-2">Error</h3>
                    <p className="text-gray-600 mb-4">{error}</p>
                    <button 
                        onClick={() => fetchMatchHistory()}
                        className="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors"
                    >
                        Coba Lagi
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <div className="bg-white shadow-sm border-b border-gray-200">
                <div className="flex items-center p-4">
                    <button 
                        onClick={onBack}
                        className="mr-3 p-2 hover:bg-gray-100 rounded-full transition-colors"
                    >
                        <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <h1 className="text-xl font-semibold text-gray-900">Riwayat Pertandingan</h1>
                </div>
            </div>

            {/* Statistics Cards */}
            {matchData.statistics && (
                <div className="p-4">
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <div className="bg-white rounded-lg p-4 text-center shadow-sm">
                            <div className="text-2xl font-bold text-primary">{matchData.statistics.total_matches || 0}</div>
                            <div className="text-sm text-gray-600">Total Pertandingan</div>
                        </div>
                        <div className="bg-white rounded-lg p-4 text-center shadow-sm">
                            <div className="text-2xl font-bold text-green-600">{matchData.statistics.win_rate || 0}%</div>
                            <div className="text-sm text-gray-600">Win Rate</div>
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3 mb-6">
                        <div className="bg-white rounded-lg p-3 text-center shadow-sm">
                            <div className="text-lg font-bold text-green-600">{matchData.statistics.wins || 0}</div>
                            <div className="text-xs text-gray-600">Menang</div>
                        </div>
                        <div className="bg-white rounded-lg p-3 text-center shadow-sm">
                            <div className="text-lg font-bold text-red-600">{matchData.statistics.losses || 0}</div>
                            <div className="text-xs text-gray-600">Kalah</div>
                        </div>
                        <div className="bg-white rounded-lg p-3 text-center shadow-sm">
                            <div className="text-lg font-bold text-gray-600">{matchData.statistics.draws || 0}</div>
                            <div className="text-xs text-gray-600">Seri</div>
                        </div>
                    </div>
                </div>
            )}

            {/* Filters */}
            <div className="px-4 mb-4">
                <div className="bg-white rounded-lg p-4 shadow-sm">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="font-medium text-gray-900">Filter</h3>
                        <button 
                            onClick={clearFilters}
                            className="text-sm text-primary hover:text-primary-dark"
                        >
                            Reset
                        </button>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <select 
                            value={filters.result}
                            onChange={(e) => handleFilterChange('result', e.target.value)}
                            className="w-full p-2 border border-gray-300 rounded-lg text-sm"
                        >
                            <option value="">Semua Hasil</option>
                            <option value="wins">Menang</option>
                            <option value="losses">Kalah</option>
                            <option value="draws">Seri</option>
                        </select>
                        <select 
                            value={filters.per_page}
                            onChange={(e) => handleFilterChange('per_page', e.target.value)}
                            className="w-full p-2 border border-gray-300 rounded-lg text-sm"
                        >
                            <option value="10">10 per halaman</option>
                            <option value="20">20 per halaman</option>
                            <option value="50">50 per halaman</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Match List */}
            <div className="px-4 pb-6">
                {matchData.matches && matchData.matches.data && matchData.matches.data.length > 0 ? (
                    <div className="space-y-3">
                        {matchData.matches.data.map((match) => (
                            <div key={match.id} className="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                                {/* Match Header */}
                                <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center">
                                        <span className="text-sm font-medium text-gray-600">
                                            {match.event.sport.name}
                                        </span>
                                        <span className="mx-2 text-gray-400">•</span>
                                        <span className="text-sm text-gray-500">
                                            {formatDate(match.match_date)}
                                        </span>
                                    </div>
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${getResultColor(match.user_perspective.result)}`}>
                                        {match.user_perspective.result_text}
                                    </span>
                                </div>

                                {/* Event Title */}
                                <h3 className="font-semibold text-gray-900 mb-3 text-sm">
                                    {match.event.title}
                                </h3>

                                {/* Match Details */}
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                            <span className="text-white text-xs font-bold">
                                                {match.user_perspective.opponent.name.charAt(0)}
                                            </span>
                                        </div>
                                        <div>
                                            <div className="text-sm font-medium text-gray-900">
                                                vs {match.user_perspective.opponent.name}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {match.event.location_name}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-lg font-bold text-gray-900">
                                            {match.user_perspective.my_score} - {match.user_perspective.opponent_score}
                                        </div>
                                        {match.court_number && (
                                            <div className="text-xs text-gray-500">
                                                Court {match.court_number}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Match Duration */}
                                {match.match_duration_minutes && (
                                    <div className="mt-2 pt-2 border-t border-gray-100">
                                        <div className="text-xs text-gray-500">
                                            Durasi: {match.match_duration_minutes} menit
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}

                        {/* Pagination */}
                        {matchData.matches && matchData.matches.next_page_url && (
                            <div className="text-center pt-4">
                                <button 
                                    onClick={() => {
                                        // Implement pagination if needed
                                        console.log('Load more matches');
                                    }}
                                    className="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Muat Lebih Banyak
                                </button>
                            </div>
                        )}
                    </div>
                ) : (
                    /* Empty State */
                    <div className="text-center py-12">
                        <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-2">Belum Ada Riwayat</h3>
                        <p className="text-gray-600 mb-4">
                            Riwayat pertandingan akan muncul setelah Anda menyelesaikan event
                        </p>
                        <button 
                            onClick={() => onNavigate('discover')}
                            className="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary-dark transition-colors"
                        >
                            Ikuti Event
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};

export default MatchHistoryPage; 