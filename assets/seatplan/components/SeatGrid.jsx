import React from 'react';
import Seat from './Seat';

export default function SeatGrid({ seats, rows, seatMap, selectedReservation, onSeatClick }) {
    const seatsByKey = {};
    seats.forEach(s => { seatsByKey[s.row + s.number] = s; });

    const maxNumber = 13;

    return (
        <div className="space-y-1">
            {rows.map(row => {
                const numbers = seatMap[row] || [];
                const leftBlock = numbers.filter(n => n <= 5);
                const rightBlock = numbers.filter(n => n >= 6);

                return (
                    <div key={row} className="flex items-center gap-1">
                        <span className="w-6 text-xs text-gray-400 text-right font-mono shrink-0">{row}</span>

                        <div className="flex gap-0.5 justify-end" style={{ width: '140px' }}>
                            {[1, 2, 3, 4, 5].map(n => {
                                const seat = seatsByKey[row + n];
                                if (!numbers.includes(n)) {
                                    return <div key={n} className="w-7 h-7" />;
                                }
                                return (
                                    <Seat
                                        key={n}
                                        seat={seat}
                                        selectedReservation={selectedReservation}
                                        onClick={() => seat && onSeatClick(seat)}
                                    />
                                );
                            })}
                        </div>

                        <div className="w-6 shrink-0" />

                        <div className="flex gap-0.5" style={{ width: '224px' }}>
                            {[6, 7, 8, 9, 10, 11, 12, 13].map(n => {
                                const seat = seatsByKey[row + n];
                                if (!numbers.includes(n)) {
                                    return <div key={n} className="w-7 h-7" />;
                                }
                                return (
                                    <Seat
                                        key={n}
                                        seat={seat}
                                        selectedReservation={selectedReservation}
                                        onClick={() => seat && onSeatClick(seat)}
                                    />
                                );
                            })}
                        </div>

                        <span className="w-6 text-xs text-gray-400 font-mono shrink-0">{row}</span>
                    </div>
                );
            })}
        </div>
    );
}
