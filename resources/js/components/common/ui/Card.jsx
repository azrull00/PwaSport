import React from 'react';

const Card = ({
  children,
  title,
  subtitle,
  actions,
  padding = 'normal',
  className = '',
  headerClassName = '',
  bodyClassName = '',
  noPadding = false,
  loading = false,
  error = null,
}) => {
  const paddingMap = {
    small: 'p-4',
    normal: 'p-6',
    large: 'p-8',
  };

  const renderHeader = () => {
    if (!title && !subtitle && !actions) return null;

    return (
      <div className={`flex items-center justify-between mb-4 ${headerClassName}`}>
        <div>
          {title && (
            <h3 className="text-lg font-medium text-gray-900">
              {title}
            </h3>
          )}
          {subtitle && (
            <p className="mt-1 text-sm text-gray-500">
              {subtitle}
            </p>
          )}
        </div>
        {actions && (
          <div className="flex items-center space-x-2">
            {actions}
          </div>
        )}
      </div>
    );
  };

  const renderContent = () => {
    if (loading) {
      return (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>
      );
    }

    if (error) {
      return (
        <div className="bg-red-50 text-red-500 p-4 rounded-lg">
          {error}
        </div>
      );
    }

    return (
      <div className={bodyClassName}>
        {children}
      </div>
    );
  };

  return (
    <div className={`bg-white rounded-lg shadow ${!noPadding ? paddingMap[padding] : ''} ${className}`}>
      {renderHeader()}
      {renderContent()}
    </div>
  );
};

export default Card; 