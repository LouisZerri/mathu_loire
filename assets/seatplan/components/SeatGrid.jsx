import React from 'react';
import Seat from './Seat';

const EMPTY_SEAT = <div className="w-10 h-6 mx-0.5" />;

export default function SeatGrid({ seats, rows, seatMap, selectedReservation, onSeatClick, onSeatContextMenu }) {
    const seatsByKey = {};
    seats.forEach(s => { seatsByKey[s.row + s.number] = s; });

    const renderRow = (row, hideLeftLabel = false) => {
        const numbers = seatMap[row] || [];

        return (
            <div key={row} className="flex items-center gap-2 my-1.5">
                <div className="w-6 shrink-0 text-xs text-gray-600 text-center font-semibold">
                    {!hideLeftLabel && row}
                </div>

                <div className="flex gap-1 justify-end" style={{ width: '240px' }}>
                    {[1, 2, 3, 4, 5].map(n => {
                        if (!numbers.includes(n)) return <div key={n} className="w-10 h-6" />;
                        const seat = seatsByKey[row + n];
                        return (
                            <Seat
                                key={n}
                                seat={seat}
                                selectedReservation={selectedReservation}
                                onClick={() => seat && onSeatClick(seat)}
                                onContextMenu={(e) => seat && onSeatContextMenu(e, seat)}
                            />
                        );
                    })}
                </div>

                <div className="w-6 shrink-0 text-xs text-gray-600 text-center font-semibold">{row}</div>

                <div className="flex gap-1" style={{ width: '304px' }}>
                    {[6, 7, 8, 9, 10, 11, 12, 13].map(n => {
                        if (!numbers.includes(n)) return null;
                        const seat = seatsByKey[row + n];
                        return (
                            <Seat
                                key={n}
                                seat={seat}
                                selectedReservation={selectedReservation}
                                onClick={() => seat && onSeatClick(seat)}
                                onContextMenu={(e) => seat && onSeatContextMenu(e, seat)}
                            />
                        );
                    })}
                </div>
            </div>
        );
    };

    return (
        <div className="bg-white border-2 border-gray-300 p-6 inline-block">
            {/* Rangée R (fond de salle) */}
            {renderRow('R', true)}

            {/* Accès Salle Haut */}
            <div className="flex items-center gap-2 my-3">
                <div className="px-3 py-1 border border-gray-300 rounded text-xs text-gray-600 bg-gray-50">
                    Accès Salle Haut
                </div>
                <div className="flex-1 h-px bg-gray-300"></div>
            </div>

            {/* Rangée P */}
            {renderRow('P')}

            {/* Rangées O à D */}
            {['O', 'N', 'M', 'L', 'K', 'J', 'I', 'H', 'G', 'F', 'E', 'D'].map(row => renderRow(row))}

            {/* Accès Salle Bas */}
            <div className="flex items-center gap-2 my-3">
                <div className="px-3 py-1 border border-gray-300 rounded text-xs text-gray-600 bg-gray-50">
                    Accès Salle Bas
                </div>
                <div className="flex-1 h-px bg-gray-300"></div>
            </div>

            {/* Rangées C, B, A */}
            {['C', 'B', 'A'].map(row => renderRow(row))}

            {/* Scène */}
            <div className="mt-6 flex justify-center">
                <div className="bg-gray-900 text-white text-center py-3 rounded-t-full" style={{ width: '500px' }}>
                    <span className="text-xl font-serif italic">Scène</span>
                </div>
            </div>
        </div>
    );
}
