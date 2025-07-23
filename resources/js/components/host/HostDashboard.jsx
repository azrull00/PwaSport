import React, { useState, useEffect } from 'react';
import axios from 'axios';
import VenueManagement from './VenueManagement';
import CourtManagement from './CourtManagement';
import MatchmakingMonitor from './MatchmakingMonitor';
import DashboardStats from './DashboardStats';

const HostDashboard = ({ user, userToken, onNavigate }) => {
    const [activeTab, setActiveTab] = useState('overview');
    const [venues, setVenues] = useState([]);
    const [selectedVenue, setSelectedVenue] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Fetch venues for the host
    const fetchVenues = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/host/venues');
            
            if (response.data && response.data.data) {
                setVenues(response.data.data.venues || []);
                if (response.data.data.venues?.length > 0) {
                    setSelectedVenue(response.data.data.venues[0]);
                }
            }
        } catch (error) {
            console.error('Error fetching venues:', error);
            setError('Failed to load venues. Please try again later.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchVenues();
    }, []);

    const handleVenueSelect = (venue) => {
        setSelectedVenue(venue);
    };

    const handleTabChange = (tab) => {
        setActiveTab(tab);
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-4">
                <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="p-4 space-y-4">
            {/* Venue Selection */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Venue Anda</h2>
                {venues.length === 0 ? (
                    <div className="text-center py-8">
                        <div className="text-6xl mb-4">🏟️</div>
                        <h3 className="font-semibold text-gray-900 mb-2">Belum ada venue</h3>
                        <p className="text-gray-600 text-sm mb-4">Tambahkan venue pertama Anda untuk memulai</p>
                        <button
                            onClick={() => onNavigate('venues')}
                            className="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors"
                        >
                            Tambah Venue
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3">
                        {venues.map((venue) => (
                            <div
                                key={venue.id}
                                onClick={() => handleVenueSelect(venue)}
                                className={`cursor-pointer p-4 rounded-xl border transition-colors ${
                                    selectedVenue?.id === venue.id
                                        ? 'border-primary bg-primary/5'
                                        : 'border-gray-200 hover:border-primary/50'
                                }`}
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <h3 className="font-medium text-gray-900">{venue.name}</h3>
                                        <p className="text-sm text-gray-600 mt-1">{venue.address}</p>
                                        <div className="flex items-center mt-2">
                                            <span className="text-sm text-gray-500">
                                                🏸 {venue.courts_count || 0} Courts
                                            </span>
                                        </div>
                                    </div>
                                    {selectedVenue?.id === venue.id && (
                                        <div className="ml-3">
                                            <div className="w-6 h-6 bg-primary rounded-full flex items-center justify-center">
                                                <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Dashboard Content */}
            {selectedVenue && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    {/* Tabs */}
                    <div className="px-4 pt-4">
                        <div className="flex bg-gray-100 rounded-xl p-1">
                            <button
                                onClick={() => handleTabChange('overview')}
                                className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors text-sm ${
                                    activeTab === 'overview'
                                        ? 'bg-white text-primary shadow-sm'
                                        : 'text-gray-600 hover:text-gray-800'
                                }`}
                            >
                                Overview
                            </button>
                            <button
                                onClick={() => handleTabChange('courts')}
                                className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors text-sm ${
                                    activeTab === 'courts'
                                        ? 'bg-white text-primary shadow-sm'
                                        : 'text-gray-600 hover:text-gray-800'
                                }`}
                            >
                                Courts
                            </button>
                            <button
                                onClick={() => handleTabChange('matchmaking')}
                                className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors text-sm ${
                                    activeTab === 'matchmaking'
                                        ? 'bg-white text-primary shadow-sm'
                                        : 'text-gray-600 hover:text-gray-800'
                                }`}
                            >
                                Matchmaking
                            </button>
                        </div>
                    </div>

                    {/* Content */}
                    <div className="p-6">
                        {activeTab === 'overview' && (
                            <DashboardStats venue={selectedVenue} />
                        )}
                        {activeTab === 'courts' && (
                            <CourtManagement venue={selectedVenue} />
                        )}
                        {activeTab === 'matchmaking' && (
                            <MatchmakingMonitor venue={selectedVenue} />
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

export default HostDashboard;