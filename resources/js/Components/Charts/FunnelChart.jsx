import {
    ResponsiveContainer,
    FunnelChart as ReFunnelChart,
    Funnel,
    LabelList,
    Tooltip,
} from 'recharts';

const COLORS = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe'];

export default function FunnelChart({ data = [], nameKey = 'name', valueKey = 'value', height = 300 }) {
    const colored = data.map((d, i) => ({ ...d, fill: COLORS[i % COLORS.length] }));

    return (
        <ResponsiveContainer width="100%" height={height}>
            <ReFunnelChart>
                <Tooltip
                    contentStyle={{
                        background: 'var(--tooltip-bg, #fff)',
                        border: '1px solid #e5e7eb',
                        borderRadius: 8,
                        fontSize: 12,
                    }}
                    formatter={(value, name) => [value.toLocaleString(), name]}
                />
                <Funnel
                    dataKey={valueKey}
                    data={colored}
                    isAnimationActive
                >
                    <LabelList
                        position="center"
                        fill="#fff"
                        fontSize={12}
                        dataKey={nameKey}
                    />
                </Funnel>
            </ReFunnelChart>
        </ResponsiveContainer>
    );
}
