import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import axios from 'axios';

const DistanceDisplay = ({
  targetLocation,
  currentLocation,
  showUnit = true,
  className = '',
  updateInterval = 30000 // Update every 30 seconds by default
}) => {
  const [distance, setDistance] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const calculateDistance = async (start, end) => {
    try {
      const response = await axios.post('/location/calculate-distance', {
        start_latitude: start.lat,
        start_longitude: start.lng,
        end_latitude: end.lat,
        end_longitude: end.lng
      });

      if (response.data.status === 'success') {
        setDistance(response.data.data.distance);
      } else {
        throw new Error(response.data.message);
      }
      setLoading(false);
    } catch (err) {
      console.error('Error calculating distance:', err);
      setError('Failed to calculate distance');
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!targetLocation || !currentLocation) {
      setError('Location data is missing');
      setLoading(false);
      return;
    }

    calculateDistance(currentLocation, targetLocation);

    // Set up periodic updates if updateInterval > 0
    if (updateInterval > 0) {
      const intervalId = setInterval(() => {
        calculateDistance(currentLocation, targetLocation);
      }, updateInterval);

      return () => clearInterval(intervalId);
    }
  }, [targetLocation, currentLocation, updateInterval]);

  const formatDistance = (distanceInKm) => {
    if (distanceInKm < 1) {
      const meters = Math.round(distanceInKm * 1000);
      return showUnit ? `${meters}m` : meters.toString();
    }
    return showUnit ? `${distanceInKm.toFixed(1)}km` : distanceInKm.toFixed(1);
  };

  if (loading) {
    return <span className={`inline-block ${className}`}>Calculating...</span>;
  }

  if (error) {
    return <span className={`inline-block text-red-500 ${className}`}>{error}</span>;
  }

  if (distance === null) {
    return <span className={`inline-block ${className}`}>--</span>;
  }

  return (
    <span className={`inline-block ${className}`} title={`${distance.toFixed(2)} kilometers`}>
      {formatDistance(distance)}
    </span>
  );
};

DistanceDisplay.propTypes = {
  targetLocation: PropTypes.shape({
    lat: PropTypes.number.isRequired,
    lng: PropTypes.number.isRequired
  }),
  currentLocation: PropTypes.shape({
    lat: PropTypes.number.isRequired,
    lng: PropTypes.number.isRequired
  }),
  showUnit: PropTypes.bool,
  className: PropTypes.string,
  updateInterval: PropTypes.number
};

export default DistanceDisplay;