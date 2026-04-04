import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import SeatPlanApp from './components/SeatPlanApp';

function App() {
    const container = document.getElementById('seatplan-root');
    const representations = JSON.parse(container.dataset.representations || '[]');
    const [representationId, setRepresentationId] = useState('');

    return (
        <>
            <div className="mb-6">
                <label className="text-sm text-gray-500 mr-2">Représentation :</label>
                <select
                    value={representationId}
                    onChange={e => setRepresentationId(e.target.value)}
                    className="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900"
                >
                    <option value="">-- Choisir --</option>
                    {representations.map(rep => (
                        <option key={rep.id} value={rep.id}>{rep.label}</option>
                    ))}
                </select>
            </div>

            {representationId && <SeatPlanApp representationId={representationId} />}
            {!representationId && <p className="text-gray-500 text-sm">Sélectionnez une représentation pour afficher le plan de salle.</p>}
        </>
    );
}

const container = document.getElementById('seatplan-root');
if (container) {
    createRoot(container).render(<App />);
}
