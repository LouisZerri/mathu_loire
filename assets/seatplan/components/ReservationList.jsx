import React from 'react';

export default function ReservationList({ reservations, selectedReservation, onSelect }) {
    return (
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div className="px-4 py-3 border-b border-gray-200">
                <h3 className="text-sm font-semibold text-gray-900">Réservations</h3>
                <p className="text-xs text-gray-500 mt-0.5">
                    Sélectionnez une réservation puis cliquez sur un siège
                </p>
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
                            Annuler
                        </button>
                    </div>
                    <div className="text-xs text-blue-600 mt-0.5">
                        {selectedReservation.assignedCount}/{selectedReservation.totalPlaces} placé(es)
                    </div>
                </div>
            )}

            <div className="max-h-[500px] overflow-y-auto divide-y divide-gray-100">
                {reservations.map(reservation => {
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

                {reservations.length === 0 && (
                    <p className="px-4 py-6 text-center text-xs text-gray-400">
                        Aucune réservation validée.
                    </p>
                )}
            </div>
        </div>
    );
}
