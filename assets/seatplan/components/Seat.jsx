import React from 'react';

const STATUS_STYLES = {
    available: 'bg-green-100 border-green-300 hover:bg-green-200 cursor-pointer',
    assigned: 'bg-blue-200 border-blue-400 hover:bg-blue-300 cursor-pointer',
    blocked: 'bg-orange-200 border-orange-400 hover:bg-orange-300 cursor-pointer',
    broken: 'bg-gray-300 border-gray-400 cursor-not-allowed opacity-50',
};

export default function Seat({ seat, selectedReservation, onClick }) {
    if (!seat) return <div className="w-7 h-7" />;

    const isHighlighted = selectedReservation && seat.reservationId === selectedReservation.id;
    const baseStyle = STATUS_STYLES[seat.status] || STATUS_STYLES.available;
    const highlight = isHighlighted ? 'ring-2 ring-blue-500' : '';

    return (
        <button
            className={`w-7 h-7 rounded-sm border text-[9px] font-mono leading-none flex items-center justify-center transition-colors ${baseStyle} ${highlight}`}
            onClick={onClick}
            title={`${seat.row}${seat.number}${seat.spectatorName ? ' — ' + seat.spectatorName : ''}`}
        >
            {seat.number}
        </button>
    );
}
