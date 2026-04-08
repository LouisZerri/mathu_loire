import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import SeatPlanApp from './components/SeatPlanApp';

function App() {
    const container = document.getElementById('seatplan-root');
    const representations = JSON.parse(container.dataset.representations || '[]');
    const preselectedRep = container.dataset.preselectedRepresentation || '';
    const preselectedResa = container.dataset.preselectedReservation || '';

    const [representationId, setRepresentationId] = useState(preselectedRep);

    return (
        <>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h2 className="text-2xl font-bold text-gray-900">Plan de salle</h2>
                <select
                    value={representationId}
                    onChange={e => setRepresentationId(e.target.value)}
                    className="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 sm:max-w-sm"
                >
                    <option value="">-- Choisir une représentation --</option>
                    {representations.map(rep => (
                        <option key={rep.id} value={rep.id}>{rep.label}</option>
                    ))}
                </select>
            </div>

            {representationId && (
                <div className="mt-6">
                    <SeatPlanApp representationId={representationId} preselectedReservationId={preselectedResa} />
                </div>
            )}
            {!representationId && (
                <div className="mt-24 max-w-2xl mx-auto text-center">
                    <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-gray-100 mb-4">
                        <svg className="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/></svg>
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-2">Sélectionnez une représentation</h3>
                    <p className="text-sm text-gray-500 mb-6">
                        Choisissez une date dans le menu ci-dessus pour afficher le plan de salle et gérer le placement des spectateurs.
                    </p>

                    <div className="grid sm:grid-cols-3 gap-3 text-left">
                        <div className="bg-white border border-gray-200 rounded-lg p-4">
                            <div className="text-xs font-semibold text-gray-900 mb-1">Placer un spectateur</div>
                            <p className="text-xs text-gray-500">Sélectionnez une réservation dans la liste, puis cliquez sur un siège libre.</p>
                        </div>
                        <div className="bg-white border border-gray-200 rounded-lg p-4">
                            <div className="text-xs font-semibold text-gray-900 mb-1">Échanger deux sièges</div>
                            <p className="text-xs text-gray-500">Clic droit sur un siège assigné → « Échanger avec un autre siège ».</p>
                        </div>
                        <div className="bg-white border border-gray-200 rounded-lg p-4">
                            <div className="text-xs font-semibold text-gray-900 mb-1">Bloquer ou marquer cassé</div>
                            <p className="text-xs text-gray-500">Cliquez sur un siège libre pour le bloquer, ou clic droit pour le marquer cassé.</p>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

const container = document.getElementById('seatplan-root');
if (container) {
    createRoot(container).render(<App />);
}
