import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

vi.mock('@/Layouts/ClientLayout', () => ({ default: ({ children }) => children }))
vi.mock('@xyflow/react', () => ({
    Handle: () => null,
    NodeToolbar: () => null,
    Position: { Top: 'top', Bottom: 'bottom', Left: 'left', Right: 'right' },
}))
import { MindNode, horizontalTree } from '@/Pages/AI/MindMaps/Show'

describe('mind map long topics', () => {
    it('renders the complete multiline text with wrapping and no line clamp', () => {
        const label = ('Texto completo com acentuação. ' + 'x'.repeat(500) + '\n').repeat(30)
        render(<MindNode id="root" data={{ label, level: 0, color: '#7c3aed' }} />)
        const topic = screen.getByText((_, element) => element.tagName === 'P' && element.textContent === label)
        expect(topic.textContent).toBe(label)
        expect(topic.className).toContain('whitespace-pre-wrap')
        expect(topic.className).toContain('[overflow-wrap:anywhere]')
        expect(topic.className).not.toContain('line-clamp')
    })

    it('leaves space for a tall parent before the next branch', () => {
        const nodes = [
            { id: 'root', measured: { height: 100 } },
            { id: 'long', measured: { height: 1500 } },
            { id: 'child', measured: { height: 100 } },
            { id: 'sibling', measured: { height: 200 } },
        ]
        const edges = [
            { source: 'root', target: 'long' },
            { source: 'long', target: 'child' },
            { source: 'root', target: 'sibling' },
        ]
        const { vertical } = horizontalTree(nodes, edges, nodes[0])
        expect(vertical.get('sibling')).toBeGreaterThanOrEqual(vertical.get('long') + 1500 + 48)
    })
})