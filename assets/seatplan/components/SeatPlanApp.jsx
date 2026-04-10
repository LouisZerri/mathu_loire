import React, { useState, useEffect, useCallback, useRef } from 'react';
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

export default function SeatPlanApp({ representationId, preselectedReservationId }) {
    const [seats, setSeats] = useState([]);
    const [reservations, setReservations] = useState([]);
    const [selectedReservation, setSelectedReservation] = useState(null);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState(null);
    const [contextMenu, setContextMenu] = useState(null);
    const [swapSource, setSwapSource] = useState(null);
    const planRef = useRef(null);
    const [planHeight, setPlanHeight] = useState(null);

    useEffect(() => {
        if (!planRef.current) return;
        const update = () => setPlanHeight(planRef.current?.offsetHeight ?? null);
        update();
        const ro = new ResizeObserver(update);
        ro.observe(planRef.current);
        window.addEventListener('resize', update);
        return () => {
            ro.disconnect();
            window.removeEventListener('resize', update);
        };
    }, [loading]);

    const api = useCallback(async (url, options = {}) => {
        try {
            const res = await fetch(url, options);
            if (!res.ok) {
                throw new Error(`Erreur serveur (${res.status})`);
            }
            return await res.json();
        } catch (e) {
            showMessage(e.message || 'Erreur réseau. Vérifiez votre connexion.', 'error');
            return null;
        }
    }, []);

    const fetchData = useCallback(async () => {
        if (!representationId) return;
        setLoading(true);
        try {
            const [seatsData, resaData] = await Promise.all([
                fetch(`/admin/plan-de-salle/api/seats/${representationId}`).then(r => r.json()),
                fetch(`/admin/plan-de-salle/api/reservations/${representationId}`).then(r => r.json()),
            ]);
            setSeats(seatsData);
            setReservations(resaData);
        } catch {
            showMessage('Erreur lors du chargement du plan de salle.', 'error');
        }
        setLoading(false);
    }, [representationId]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // Pré-sélectionner une réservation si fournie en paramètre URL
    useEffect(() => {
        if (preselectedReservationId && reservations.length > 0) {
            const found = reservations.find(r => String(r.id) === String(preselectedReservationId));
            if (found) {
                setSelectedReservation(found);
            }
        }
    }, [preselectedReservationId, reservations]);

    const showMessage = (text, type = 'success') => {
        setMessage({ text, type });
        setTimeout(() => setMessage(null), 3000);
    };

    const handleContextMenu = (e, seat) => {
        e.preventDefault();
        setContextMenu({ seat, x: e.clientX, y: e.clientY });
    };

    const closeContextMenu = () => setContextMenu(null);

    const startSwap = (seat) => {
        setSwapSource(seat);
        closeContextMenu();
        showMessage(`Sélectionnez un siège à échanger avec ${seat.row}${seat.number}`);
    };

    const cancelSwap = () => {
        setSwapSource(null);
    };

    const toggleBroken = async (seat) => {
        if (seat.status === 'assigned') {
            showMessage('Impossible : ce siège est déjà assigné.', 'error');
            closeContextMenu();
            return;
        }
        const data = await api('/admin/plan-de-salle/api/toggle-broken', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ seatId: seat.id }),
        });
        if (!data) return;
        showMessage(data.isActive ? `Siège ${seat.row}${seat.number} réparé` : `Siège ${seat.row}${seat.number} marqué cassé`);
        closeContextMenu();
        fetchData();
    };

    const handleSeatClick = async (seat) => {
        // Mode échange : si une source est sélectionnée, traiter comme cible
        if (swapSource) {
            if (swapSource.id === seat.id) {
                setSwapSource(null);
                return;
            }
            if (seat.status !== 'assigned') {
                showMessage('Vous devez sélectionner un siège assigné à une autre personne.', 'error');
                return;
            }
            const swapResult = await api('/admin/plan-de-salle/api/swap', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    seatAId: swapSource.id,
                    seatBId: seat.id,
                    representationId,
                }),
            });
            if (!swapResult) return;
            showMessage(`Sièges ${swapSource.row}${swapSource.number} ↔ ${seat.row}${seat.number} échangés`);
            setSwapSource(null);
            fetchData();
            return;
        }

        if (seat.status === 'broken') return;

        if (seat.status === 'assigned' && !selectedReservation) {
            if (confirm(`Libérer le siège ${seat.row}${seat.number} (${seat.spectatorName}) ?`)) {
                await api('/admin/plan-de-salle/api/unassign', {
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
            // Si le siège est déjà assigné à la réservation sélectionnée, le libérer
            if (seat.status === 'assigned' && seat.reservationId === selectedReservation.id) {
                if (confirm(`Libérer le siège ${seat.row}${seat.number} ?`)) {
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

            // Empêcher de placer plus de sièges que le nombre de places réservées
            if (selectedReservation.assignedCount >= selectedReservation.totalPlaces) {
                showMessage(`Toutes les places de cette réservation sont déjà placées (${selectedReservation.assignedCount}/${selectedReservation.totalPlaces}).`, 'error');
                return;
            }

            // Si le siège est déjà assigné à quelqu'un d'autre, demander confirmation
            let previousReservationId = null;
            if (seat.status === 'assigned' && seat.reservationId !== selectedReservation.id) {
                if (!confirm(`Le siège ${seat.row}${seat.number} est déjà assigné à ${seat.spectatorName}. Voulez-vous le réassigner à ${selectedReservation.spectatorName} ?`)) {
                    return;
                }
                previousReservationId = seat.reservationId;
            }

            const assignResult = await api('/admin/plan-de-salle/api/assign', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    seatId: seat.id,
                    reservationId: selectedReservation.id,
                    representationId,
                }),
            });
            if (!assignResult) return;
            showMessage(`Siège ${seat.row}${seat.number} → ${selectedReservation.spectatorName}`);

            // Si on a déplacé le siège d'une autre réservation, basculer sur elle pour la replacer
            if (previousReservationId) {
                await fetchData();
                const updatedReservations = await api(`/admin/plan-de-salle/api/reservations/${representationId}`);
                if (!updatedReservations) return;
                const previous = updatedReservations.find(r => r.id === previousReservationId);
                if (previous && confirm(`Le déplacement est effectué. Replacer maintenant ${previous.spectatorName} (${previous.assignedCount}/${previous.totalPlaces} placé) ?`)) {
                    setSelectedReservation(previous);
                } else {
                    fetchData();
                }
            } else {
                fetchData();
            }
            return;
        }

        if (seat.status === 'available' && !selectedReservation) {
            await api('/admin/plan-de-salle/api/block', {
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
            <div ref={planRef} className="flex flex-col items-center min-w-0 w-full xl:w-auto">
                {message && (
                    <div className={`mb-4 px-4 py-2 rounded-lg text-sm w-full max-w-md ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
                        {message.text}
                    </div>
                )}

                {swapSource && (
                    <div className="mb-4 px-4 py-2 rounded-lg text-sm w-full max-w-md bg-blue-50 text-blue-800 border border-blue-200 flex items-center justify-between">
                        <span>Échange en cours — cliquez sur un autre siège assigné</span>
                        <button onClick={cancelSwap} className="text-xs text-blue-600 hover:text-blue-800 ml-2">Annuler</button>
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
                            {contextMenu.seat.status === 'assigned' && (
                                <button
                                    onClick={() => startSwap(contextMenu.seat)}
                                    className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    Échanger avec un autre siège
                                </button>
                            )}
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

            <div className="w-full xl:w-80 shrink-0 flex flex-col gap-4 min-h-0" style={planHeight ? { height: planHeight + 'px' } : undefined}>
                <ReservationList
                    reservations={reservations}
                    selectedReservation={selectedReservation}
                    onSelect={setSelectedReservation}
                />
                <div className="bg-white border border-gray-200 rounded-lg p-4 shrink-0">
                    <SeatLegend />
                </div>
            </div>
        </div>
    );
}
