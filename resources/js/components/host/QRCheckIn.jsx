import React, { useState, useEffect } from 'react';
import { QrReader } from 'react-qr-reader';
import axios from 'axios';
import { useParams } from 'react-router-dom';
import { toast } from 'react-hot-toast';

const QRCheckIn = ({ eventId, onSuccessfulCheckIn }) => {
    const [scanning, setScanning] = useState(false);
    const [cameraError, setCameraError] = useState(null);
    const [recentScans, setRecentScans] = useState([]);
    
    // Fallback to useParams if eventId is not provided as prop
    const { eventId: paramEventId } = useParams();
    const currentEventId = eventId || paramEventId;

    const handleScan = async (data) => {
        if (data && currentEventId) {
            try {
                const response = await axios.post(`/host/events/${currentEventId}/check-in/qr`, {
                    qr_code: data,
                    check_in_type: 'participant' // Default to participant, will be overridden by QR data
                });

                if (response.data.status === 'success') {
                    toast.success(response.data.message);
                    setRecentScans(prev => [{
                        timestamp: new Date(),
                        success: true,
                        message: response.data.message,
                        data: response.data.data
                    }, ...prev].slice(0, 10)); // Keep last 10 scans
                    
                    // Call the callback function if provided
                    if (onSuccessfulCheckIn) {
                        onSuccessfulCheckIn(response.data.data);
                    }
                }
            } catch (error) {
                toast.error(error.response?.data?.message || 'Failed to process QR code');
                setRecentScans(prev => [{
                    timestamp: new Date(),
                    success: false,
                    message: error.response?.data?.message || 'Failed to process QR code'
                }, ...prev].slice(0, 10));
            }
        }
    };

    const handleError = (err) => {
        setCameraError(err);
        toast.error('Camera error: ' + err.message);
    };

    const toggleScanning = () => {
        setScanning(!scanning);
        if (!scanning) {
            setCameraError(null);
        }
    };

    if (!currentEventId) {
        return (
            <div className="p-4">
                <div className="text-center py-8">
                    <div className="text-red-500 text-lg">Error: Event ID tidak ditemukan</div>
                    <p className="text-gray-600 mt-2">Silakan pilih event terlebih dahulu</p>
                </div>
            </div>
        );
    }

    return (
        <div className="p-4">
            <h2 className="text-2xl font-bold mb-4">QR Code Check-in</h2>
            
            <div className="mb-4">
                <button
                    onClick={toggleScanning}
                    className={`px-4 py-2 rounded ${
                        scanning 
                            ? 'bg-red-500 hover:bg-red-600' 
                            : 'bg-blue-500 hover:bg-blue-600'
                    } text-white`}
                >
                    {scanning ? 'Stop Scanning' : 'Start Scanning'}
                </button>
            </div>

            {scanning && (
                <div className="mb-6">
                    <div className="max-w-md mx-auto border-4 border-blue-500 rounded-lg overflow-hidden">
                        <QrReader
                            onResult={(result, error) => {
                                if (!!result) {
                                    handleScan(result?.text);
                                }
                                if (!!error) {
                                    handleError(error);
                                }
                            }}
                            constraints={{
                                facingMode: 'environment'
                            }}
                            className="w-full"
                            style={{ width: '100%' }}
                        />
                    </div>
                    {cameraError && (
                        <div className="mt-2 text-red-500">
                            Camera Error: {cameraError.message}
                        </div>
                    )}
                </div>
            )}

            <div className="mt-6">
                <h3 className="text-xl font-semibold mb-3">Recent Scans</h3>
                <div className="space-y-2">
                    {recentScans.map((scan, index) => (
                        <div
                            key={index}
                            className={`p-3 rounded ${
                                scan.success ? 'bg-green-100' : 'bg-red-100'
                            }`}
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <p className={scan.success ? 'text-green-700' : 'text-red-700'}>
                                        {scan.message}
                                    </p>
                                    {scan.data && (
                                        <p className="text-sm text-gray-600">
                                            {scan.data.participant 
                                                ? `Participant: ${scan.data.participant.user?.name || 'Guest'}`
                                                : `Guest: ${scan.data.guest_player?.name || 'Unknown'}`
                                            }
                                        </p>
                                    )}
                                </div>
                                <span className="text-xs text-gray-500">
                                    {scan.timestamp.toLocaleTimeString()}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-6 p-4 bg-gray-100 rounded">
                <h3 className="text-lg font-semibold mb-2">Instructions</h3>
                <ul className="list-disc list-inside space-y-1 text-gray-700">
                    <li>Point the camera at the participant's QR code</li>
                    <li>The code will be scanned automatically</li>
                    <li>A success message will appear when check-in is complete</li>
                    <li>If there's an error, check that the QR code is valid and try again</li>
                </ul>
            </div>
        </div>
    );
};

export default QRCheckIn;