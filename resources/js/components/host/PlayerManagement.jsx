import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { toast } from 'react-hot-toast';
import QRCheckIn from './QRCheckIn';
import { FaQrcode, FaUserPlus, FaSearch, FaFilter } from 'react-icons/fa';

const PlayerManagement = ({ eventId }) => {
    const [participants, setParticipants] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showQRScanner, setShowQRScanner] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [filterStatus, setFilterStatus] = useState('all');

    useEffect(() => {
        if (eventId) {
            fetchParticipants();
        } else {
            setLoading(false);
            setParticipants([]);
        }
    }, [eventId]);

    const fetchParticipants = async () => {
        if (!eventId) {
            toast.error('No event selected');
            return;
        }

        try {
            const response = await axios.get(`/events/${eventId}/participants`);
            if (response.data && response.data.data) {
                setParticipants(response.data.data.participants || []);
            } else {
                setParticipants([]);
                toast.error('Invalid response format from server');
            }
        } catch (error) {
            console.error('Failed to fetch participants:', error);
            toast.error(error.response?.data?.message || 'Failed to fetch participants');
            setParticipants([]);
        } finally {
            setLoading(false);
        }
    };

    const handleManualCheckIn = async (participantId) => {
        if (!eventId) {
            toast.error('No event selected');
            return;
        }

        try {
            await axios.post(`/events/${eventId}/check-in/${participantId}`);
            toast.success('Player checked in successfully');
            fetchParticipants();
        } catch (error) {
            console.error('Failed to check in player:', error);
            toast.error(error.response?.data?.message || 'Failed to check in player');
        }
    };

    const handleQRCheckIn = (participant) => {
        fetchParticipants();
        setShowQRScanner(false);
    };

    const filteredParticipants = participants.filter(participant => {
        const matchesSearch = participant.user.name.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesFilter = filterStatus === 'all' || participant.status === filterStatus;
        return matchesSearch && matchesFilter;
    });

    const PlayerCard = ({ participant }) => (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <img
                        className="h-12 w-12 rounded-full object-cover"
                        src={participant.user.profile_picture || '/default-avatar.png'}
                        alt=""
                    />
                    <div>
                        <h3 className="font-medium text-gray-900">{participant.user.name}</h3>
                        <p className="text-sm text-gray-600">{participant.user.email}</p>
                        <div className="flex items-center mt-1">
                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                participant.status === 'checked_in' ? 'bg-green-100 text-green-700' :
                                participant.status === 'playing' ? 'bg-blue-100 text-blue-700' :
                                participant.status === 'completed' ? 'bg-gray-100 text-gray-700' :
                                'bg-yellow-100 text-yellow-700'
                            }`}>
                                {participant.status === 'checked_in' ? 'Sudah Check-in' :
                                 participant.status === 'playing' ? 'Sedang Bermain' :
                                 participant.status === 'completed' ? 'Selesai' :
                                 'Terdaftar'}
                            </span>
                        </div>
                    </div>
                </div>
                <div className="flex flex-col items-end">
                    <div className="text-xs text-gray-500 mb-2">
                        {participant.checked_in_at ? 
                            new Date(participant.checked_in_at).toLocaleString() : 
                            'Belum check-in'
                        }
                    </div>
                    {participant.status === 'registered' && (
                        <button
                            onClick={() => handleManualCheckIn(participant.id)}
                            className="bg-primary text-white px-3 py-1 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors"
                        >
                            Check In
                        </button>
                    )}
                </div>
            </div>
        </div>
    );

    return (
        <div className="p-4 space-y-4">
            {/* Header */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Manajemen Player</h2>
                
                {/* Search and Filter */}
                <div className="space-y-3">
                    <div className="relative">
                        <input
                            type="text"
                            placeholder="Cari player..."
                            className="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        <FaSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                    </div>

                    <div className="flex flex-col sm:flex-row gap-3">
                        <select
                            className="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                            value={filterStatus}
                            onChange={(e) => setFilterStatus(e.target.value)}
                        >
                            <option value="all">Semua Status</option>
                            <option value="registered">Terdaftar</option>
                            <option value="checked_in">Sudah Check-in</option>
                            <option value="playing">Sedang Bermain</option>
                            <option value="completed">Selesai</option>
                        </select>

                        <button
                            onClick={() => setShowQRScanner(!showQRScanner)}
                            className="flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors text-sm font-medium"
                        >
                            <FaQrcode />
                            {showQRScanner ? 'Sembunyikan Scanner' : 'QR Check-in'}
                        </button>
                    </div>
                </div>

                {/* QR Scanner */}
                {showQRScanner && (
                    <div className="mt-4 p-4 bg-gray-50 rounded-xl">
                        <QRCheckIn eventId={eventId} onSuccessfulCheckIn={handleQRCheckIn} />
                    </div>
                )}
            </div>

            {/* Players List */}
            <div>
                {loading ? (
                    <div className="flex justify-center items-center h-64">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                ) : (
                    <>
                        {filteredParticipants.length === 0 ? (
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                                <div className="text-center py-8">
                                    <div className="text-6xl mb-4">👥</div>
                                    <h3 className="font-semibold text-gray-900 mb-2">Belum ada player</h3>
                                    <p className="text-gray-600 text-sm">Player yang terdaftar akan muncul di sini</p>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {filteredParticipants.map((participant) => (
                                    <PlayerCard key={participant.id} participant={participant} />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
};

export default PlayerManagement; 