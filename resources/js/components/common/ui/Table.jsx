import React from 'react';

const Table = ({
  columns,
  data,
  loading = false,
  error = null,
  emptyMessage = 'No data available',
  onRowClick,
  containerClassName = '',
  tableClassName = '',
  headerClassName = '',
  bodyClassName = '',
  rowClassName = '',
  cellClassName = '',
  stickyHeader = false,
}) => {
  const renderHeader = () => (
    <thead className={`bg-gray-50 ${headerClassName}`}>
      <tr>
        {columns.map((column, index) => (
          <th
            key={column.key || index}
            className={`
              px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider
              ${stickyHeader ? 'sticky top-0 z-10 bg-gray-50' : ''}
              ${column.className || ''}
            `}
            style={column.width ? { width: column.width } : {}}
          >
            {column.title}
          </th>
        ))}
      </tr>
    </thead>
  );

  const renderBody = () => {
    if (loading) {
      return (
        <tbody className={`bg-white divide-y divide-gray-200 ${bodyClassName}`}>
          {[...Array(3)].map((_, rowIndex) => (
            <tr key={rowIndex} className="animate-pulse">
              {columns.map((_, colIndex) => (
                <td key={colIndex} className="px-6 py-4 whitespace-nowrap">
                  <div className="h-4 bg-gray-200 rounded w-3/4"></div>
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      );
    }

    if (error) {
      return (
        <tbody>
          <tr>
            <td
              colSpan={columns.length}
              className="px-6 py-4 text-center text-sm text-red-500 bg-red-50"
            >
              {error}
            </td>
          </tr>
        </tbody>
      );
    }

    if (!data || data.length === 0) {
      return (
        <tbody>
          <tr>
            <td
              colSpan={columns.length}
              className="px-6 py-4 text-center text-sm text-gray-500"
            >
              {emptyMessage}
            </td>
          </tr>
        </tbody>
      );
    }

    return (
      <tbody className={`bg-white divide-y divide-gray-200 ${bodyClassName}`}>
        {data.map((row, rowIndex) => (
          <tr
            key={row.id || rowIndex}
            onClick={() => onRowClick && onRowClick(row)}
            className={`
              ${onRowClick ? 'cursor-pointer hover:bg-gray-50' : ''}
              ${rowClassName}
            `}
          >
            {columns.map((column, colIndex) => (
              <td
                key={column.key || colIndex}
                className={`px-6 py-4 whitespace-nowrap text-sm text-gray-900 ${cellClassName} ${column.cellClassName || ''}`}
              >
                {column.render
                  ? column.render(row[column.key], row)
                  : row[column.key]}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    );
  };

  return (
    <div className={`flex flex-col ${containerClassName}`}>
      <div className="overflow-x-auto">
        <div className="inline-block min-w-full align-middle">
          <table className={`min-w-full divide-y divide-gray-200 ${tableClassName}`}>
            {renderHeader()}
            {renderBody()}
          </table>
        </div>
      </div>
    </div>
  );
};

export default Table; 