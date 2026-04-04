import React, { useState, useEffect, useCallback } from 'react';
import SeatGrid from './SeatGrid';
import ReservationList from './ReservationList';
import SeatLegend from './SeatLegend';

const SEAT_MAP = {
    A: [7, 8, 9, 10],
    B: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    C: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    D: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    E: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    F: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    G: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    H: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    I: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    J: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    K: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    L: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    M: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    N: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    O: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    P: [6, 7, 8, 9, 10, 11],
    R: [4, 5, 6, 7, 8, 9, 10, 11, 12, 13],
};

const ROWS = ['R', 'P', 'O', 'N', 'M', 'L', 'K', 'J', 'I', 'H', 'G', 'F', 'E', 'D', 'C', 'B', 'A'];

export default function SeatPlanApp({ representationId }) {
    const [seats, setSeats] = useState([]);
    const [reservations, setReservations] = useState([]);
    const [selectedReservation, setSelectedReservation] = useState(null);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);

    const fetchData = useCallback(async () => {
        if (!representationId) return;
        setLoading(true);
        const [seatsRes, resaRes] = await Promise.all([
            fetch(`/admin/plan-de-salle/api/seats/${representationId}`),
            fetch(`/admin/plan-de-salle/api/reservations/${representationId}`),
        ]);
        setSeats(await seatsRes.json());
        setReservations(await resaRes.json());
        setLoading(false);
    }, [representationId]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const showMessage = (text, type = 'success') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3000);
    };

    const handleSeatClick = async (seat) => {
        if (seat.status === 'broken') return;

        if (seat.status === 'assigned' && !selectedReservation) {
            if (confirm(`Libérer le siège ${seat.row}${seat.number} (${seat.spectatorName}) ?`)) {
                await fetch('/admin/plan-de-salle/api/unassign', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ seatId: seat.id, representationId }),
                });
                showMessage(`Siège ${seat.row}${seat.number} libéré`);
                fetchData();
            }
            return;
        }

        if (seat.status === 'blocked') {
            await fetch('/admin/plan-de-salle/api/unassign', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ seatId: seat.id, representationId }),
            });
            showMessage(`Siège ${seat.row}${seat.number} débloqué`);
            fetchData();
            return;
        }

        if (selectedReservation && (seat.status === 'available' || seat.status === 'assigned')) {
            await fetch('/admin/plan-de-salle/api/assign', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    seatId: seat.id,
                    reservationId: selectedReservation.id,
                    representationId,
                }),
            });
            showMessage(`Siège ${seat.row}${seat.number} → ${selectedReservation.spectatorName}`);
            fetchData();
            return;
        }

        if (seat.status === 'available' && !selectedReservation) {
            await fetch('/admin/plan-de-salle/api/block', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ seatId: seat.id, representationId }),
            });
            showMessage(`Siège ${seat.row}${seat.number} bloqué`);
            fetchData();
        }
    };

    if (!representationId) {
        return <p className="text-gray-500">Sélectionnez une représentation.</p>;
    }

    if (loading) {
        return <p className="text-gray-500">Chargement du plan de salle...</p>;
    }

    return (
        <div className="flex gap-6">
            <div className="flex-1">
                {message && (
                    <div className={`mb-4 px-4 py-2 rounded-lg text-sm ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
                        {message.text}
                    </div>
                )}

                <div className="bg-white border border-gray-200 rounded-lg p-6">
                    <div className="text-center mb-6 py-2 bg-gray-900 text-white text-sm font-medium rounded">
                        SCÈNE
                    </div>

                    <SeatGrid
                        seats={seats}
                        rows={ROWS}
                        seatMap={SEAT_MAP}
                        selectedReservation={selectedReservation}
                        onSeatClick={handleSeatClick}
                    />

                    <SeatLegend />
                </div>
            </div>

            <div className="w-72 shrink-0">
                <ReservationList
                    reservations={reservations}
                    selectedReservation={selectedReservation}
                    onSelect={setSelectedReservation}
                />
            </div>
        </div>
    );
}
