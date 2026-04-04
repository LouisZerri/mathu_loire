import React from 'react';

const items = [
    { color: 'bg-green-100 border-green-300', label: 'Libre' },
    { color: 'bg-blue-200 border-blue-400', label: 'Assigné' },
    { color: 'bg-orange-200 border-orange-400', label: 'Bloqué' },
    { color: 'bg-gray-300 border-gray-400 opacity-50', label: 'Cassé' },
];

export default function SeatLegend() {
    return (
        <div className="flex items-center justify-center gap-6 mt-6 pt-4 border-t border-gray-100">
            {items.map(item => (
                <div key={item.label} className="flex items-center gap-1.5">
                    <div className={`w-4 h-4 rounded-sm border ${item.color}`} />
                    <span className="text-xs text-gray-500">{item.label}</span>
                </div>
            ))}
        </div>
    );
}
