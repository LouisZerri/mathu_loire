import React, { useState } from 'react';

export default function ReservationList({ reservations, selectedReservation, onSelect }) {
    const [search, setSearch] = useState('');

    const filtered = reservations.filter(r => {
        if (!search.trim()) return true;
        return r.spectatorName.toLowerCase().includes(search.toLowerCase());
    });

    return (
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden flex-1 min-h-0 flex flex-col">
            <div className="px-4 py-3 border-b border-gray-200 shrink-0">
                <h3 className="text-sm font-semibold text-gray-900 mb-2">Réservations</h3>
                <input
                    type="text"
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    placeholder="Rechercher un nom…"
                    className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900"
                />
            </div>

            {selectedReservation && (
                <div className="px-4 py-2 bg-blue-50 border-b border-blue-200">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-blue-800">
                            {selectedReservation.spectatorName}
                        </span>
                        <button
                            onClick={() => onSelect(null)}
                            className="text-xs text-blue-600 hover:text-blue-800"
                        >
                            Désélectionner
                        </button>
                    </div>
                    <div className="text-xs text-blue-600 mt-0.5">
                        {selectedReservation.assignedCount}/{selectedReservation.totalPlaces} placé(es)
                    </div>
                </div>
            )}

            <div className="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-100">
                {filtered.map(reservation => {
                    const isSelected = selectedReservation?.id === reservation.id;
                    const isComplete = reservation.assignedCount >= reservation.totalPlaces;

                    return (
                        <button
                            key={reservation.id}
                            onClick={() => onSelect(isSelected ? null : reservation)}
                            className={`w-full text-left px-4 py-2.5 text-sm transition-colors hover:bg-gray-50 ${isSelected ? 'bg-blue-50' : ''}`}
                        >
                            <div className="flex items-center justify-between">
                                <span className="font-medium text-gray-900 text-xs">
                                    {reservation.spectatorName}
                                    {reservation.isPMR && (
                                        <span className="ml-1 text-blue-600">PMR</span>
                                    )}
                                </span>
                                <span className={`text-xs ${isComplete ? 'text-green-600' : 'text-orange-600'}`}>
                                    {reservation.assignedCount}/{reservation.totalPlaces}
                                </span>
                            </div>
                        </button>
                    );
                })}

                {filtered.length === 0 && (
                    <p className="px-4 py-6 text-center text-xs text-gray-400">
                        {reservations.length === 0 ? 'Aucune réservation validée.' : 'Aucun résultat.'}
                    </p>
                )}
            </div>
        </div>
    );
}
