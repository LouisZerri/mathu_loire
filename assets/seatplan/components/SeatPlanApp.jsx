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
    const [contextMenu, setContextMenu] = useState(null);

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

    const handleContextMenu = (e, seat) => {
        e.preventDefault();
        setContextMenu({ seat, x: e.clientX, y: e.clientY });
    };

    const closeContextMenu = () => setContextMenu(null);

    const toggleBroken = async (seat) => {
        if (seat.status === 'assigned') {
            showMessage('Impossible : ce siège est déjà assigné.', 'error');
            closeContextMenu();
            return;
        }
        const res = await fetch('/admin/plan-de-salle/api/toggle-broken', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seatId: seat.id }),
        });
        const data = await res.json();
        showMessage(data.isActive ? `Siège ${seat.row}${seat.number} réparé` : `Siège ${seat.row}${seat.number} marqué cassé`);
        closeContextMenu();
        fetchData();
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
            showMessage(`Siège ${seat.row}${seat.number} bloqué pour cette représentation`);
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
        <div className="flex flex-col xl:flex-row gap-4 xl:items-start xl:justify-center">
            <div className="flex flex-col items-center min-w-0 w-full xl:w-auto">
                {message && (
                    <div className={`mb-4 px-4 py-2 rounded-lg text-sm w-full max-w-md ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
                        {message.text}
                    </div>
                )}

                <div className="w-full overflow-x-auto">
                    <SeatGrid
                        seats={seats}
                        rows={ROWS}
                        seatMap={SEAT_MAP}
                        selectedReservation={selectedReservation}
                        onSeatClick={handleSeatClick}
                        onSeatContextMenu={handleContextMenu}
                    />
                </div>

                {contextMenu && (
                    <>
                        <div className="fixed inset-0 z-40" onClick={closeContextMenu}></div>
                        <div
                            className="fixed z-50 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-[200px]"
                            style={{ top: contextMenu.y, left: contextMenu.x }}
                        >
                            <div className="px-3 py-2 border-b border-gray-100">
                                <div className="text-xs text-gray-400">Siège {contextMenu.seat.row}{contextMenu.seat.number}</div>
                            </div>
                            {contextMenu.seat.status !== 'broken' && contextMenu.seat.status !== 'assigned' && (
                                <button
                                    onClick={() => toggleBroken(contextMenu.seat)}
                                    className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Marquer comme cassé
                                </button>
                            )}
                            {contextMenu.seat.status === 'broken' && (
                                <button
                                    onClick={() => toggleBroken(contextMenu.seat)}
                                    className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Marquer comme réparé
                                </button>
                            )}
                            <button
                                onClick={closeContextMenu}
                                className="w-full text-left px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 border-t border-gray-100"
                            >
                                Annuler
                            </button>
                        </div>
                    </>
                )}
            </div>

            <div className="w-full xl:w-72 shrink-0 xl:sticky xl:top-6 space-y-4">
                <div className="bg-white border border-gray-200 rounded-lg p-4">
                    <SeatLegend />
                </div>
                <ReservationList
                    reservations={reservations}
                    selectedReservation={selectedReservation}
                    onSelect={setSelectedReservation}
                />
                <p className="text-xs text-gray-400 text-center">Clic gauche : placer/bloquer · Clic droit : plus d'options</p>
            </div>
        </div>
    );
}
