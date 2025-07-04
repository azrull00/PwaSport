import React, { useState, useEffect } from 'react';
import axios from 'axios';
import VenueManagement from './VenueManagement';
import CourtManagement from './CourtManagement';
import MatchmakingMonitor from './MatchmakingMonitor';
import DashboardStats from './DashboardStats';

const HostDashboard = () => {
    const [activeTab, setActiveTab] = useState('overview');
    const [venues, setVenues] = useState([]);
    const [selectedVenue, setSelectedVenue] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Fetch venues for the host
    const fetchVenues = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/host/venues');
            setVenues(response.data.data.venues);
            if (response.data.data.venues.length > 0 && !selectedVenue) {
                setSelectedVenue(response.data.data.venues[0]);
            }
            setError(null);
        } catch (error) {
            console.error('Error fetching venues:', error);
            setError('Failed to load venues');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchVenues();
    }, []);

    const renderContent = () => {
        switch (activeTab) {
            case 'overview':
                return <DashboardStats venueId={selectedVenue?.id} />;
            case 'venues':
                return <VenueManagement />;
            case 'courts':
                return <CourtManagement venueId={selectedVenue?.id} />;
            case 'matchmaking':
                return <MatchmakingMonitor venueId={selectedVenue?.id} />;
            default:
                return null;
        }
    };

    const tabs = [
        {
            id: 'overview',
            name: 'Overview',
            icon: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
            )
        },
        {
            id: 'venues',
            name: 'Venues',
            icon: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            )
        },
        {
            id: 'courts',
            name: 'Courts',
            icon: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z" />
                </svg>
            )
        },
        {
            id: 'matchmaking',
            name: 'Matchmaking',
            icon: (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            )
        }
    ];

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Top Navigation */}
            <nav className="bg-white shadow">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex items-center">
                            <h1 className="text-xl font-bold text-gray-900">Host Dashboard</h1>
                            {venues.length > 0 && (
                                <div className="ml-8">
                                    <select
                                        value={selectedVenue?.id || ''}
                                        onChange={(e) => {
                                            const venue = venues.find(v => v.id === parseInt(e.target.value));
                                            setSelectedVenue(venue);
                                        }}
                                        className="block w-48 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
                                    >
                                        {venues.map(venue => (
                                            <option key={venue.id} value={venue.id}>
                                                {venue.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                        <div className="flex items-center">
                            <button
                                onClick={() => setActiveTab('venues')}
                                className="ml-4 px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                            >
                                Add New Venue
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Tab Navigation */}
            <div className="border-b border-gray-200 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <nav className="-mb-px flex space-x-8" aria-label="Tabs">
                        {tabs.map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`
                                    group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm
                                    ${activeTab === tab.id
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}
                                `}
                            >
                                <span className={`
                                    ${activeTab === tab.id ? 'text-primary' : 'text-gray-400 group-hover:text-gray-500'}
                                    -ml-0.5 mr-2 h-5 w-5
                                `}>
                                    {tab.icon}
                                </span>
                                {tab.name}
                            </button>
                        ))}
                    </nav>
                </div>
            </div>

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {error && (
                    <div className="mb-8 bg-red-50 text-red-500 p-4 rounded-lg">
                        {error}
                    </div>
                )}

                {loading ? (
                    <div className="flex justify-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                ) : venues.length === 0 && activeTab !== 'venues' ? (
                    <div className="text-center py-12">
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No Venues Found</h3>
                        <p className="text-gray-500">
                            Please add a venue first to manage courts and matchmaking.
                        </p>
                        <button
                            onClick={() => setActiveTab('venues')}
                            className="mt-4 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark"
                        >
                            Add Venue
                        </button>
                    </div>
                ) : (
                    renderContent()
                )}
            </main>
        </div>
    );
};

export default HostDashboard; 