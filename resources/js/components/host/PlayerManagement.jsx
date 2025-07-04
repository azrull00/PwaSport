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
        fetchParticipants();
    }, [eventId]);

    const fetchParticipants = async () => {
        try {
            const response = await axios.get(`/api/events/${eventId}/participants`);
            setParticipants(response.data.data.participants);
        } catch (error) {
            toast.error('Failed to fetch participants');
        } finally {
            setLoading(false);
        }
    };

    const handleManualCheckIn = async (participantId) => {
        try {
            await axios.post(`/api/events/${eventId}/check-in/${participantId}`);
            toast.success('Player checked in successfully');
            fetchParticipants();
        } catch (error) {
            toast.error('Failed to check in player');
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

    return (
        <div className="bg-white rounded-lg shadow-md">
            <div className="p-4 border-b">
                <h2 className="text-2xl font-semibold text-gray-800">Player Management</h2>
                
                <div className="mt-4 flex flex-wrap gap-4">
                    <div className="flex-1 min-w-[200px]">
                        <div className="relative">
                            <FaSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search players..."
                                className="w-full pl-10 pr-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <select
                            className="px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value={filterStatus}
                            onChange={(e) => setFilterStatus(e.target.value)}
                        >
                            <option value="all">All Status</option>
                            <option value="registered">Registered</option>
                            <option value="checked_in">Checked In</option>
                            <option value="playing">Playing</option>
                            <option value="completed">Completed</option>
                        </select>

                        <button
                            onClick={() => setShowQRScanner(!showQRScanner)}
                            className="flex items-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors"
                        >
                            <FaQrcode />
                            {showQRScanner ? 'Hide Scanner' : 'QR Check-in'}
                        </button>
                    </div>
                </div>

                {showQRScanner && (
                    <div className="mt-4">
                        <QRCheckIn eventId={eventId} onSuccessfulCheckIn={handleQRCheckIn} />
                    </div>
                )}
            </div>

            <div className="p-4">
                {loading ? (
                    <div className="text-center py-4">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Time</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {filteredParticipants.map((participant) => (
                                    <tr key={participant.id}>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="flex items-center">
                                                <div className="h-10 w-10 flex-shrink-0">
                                                    <img
                                                        className="h-10 w-10 rounded-full"
                                                        src={participant.user.profile_picture || '/default-avatar.png'}
                                                        alt=""
                                                    />
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {participant.user.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {participant.user.email}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                participant.status === 'checked_in' ? 'bg-green-100 text-green-800' :
                                                participant.status === 'playing' ? 'bg-blue-100 text-blue-800' :
                                                participant.status === 'completed' ? 'bg-gray-100 text-gray-800' :
                                                'bg-yellow-100 text-yellow-800'
                                            }`}>
                                                {participant.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {participant.checked_in_at ? new Date(participant.checked_in_at).toLocaleString() : 'Not checked in'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            {participant.status === 'registered' && (
                                                <button
                                                    onClick={() => handleManualCheckIn(participant.id)}
                                                    className="text-blue-600 hover:text-blue-900"
                                                >
                                                    Check In
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
};

export default PlayerManagement; 