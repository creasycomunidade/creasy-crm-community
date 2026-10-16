import { render, screen, fireEvent, cleanup, act } from '@testing-library/react';
import { afterEach, describe, it, expect, vi } from 'vitest';
import { readingTree, visibleReadingNodes } from '@/Pages/AI/MindMaps/readingGraph';
const { observed } = vi.hoisted(() => ({ observed: {} }));
vi.mock('@inertiajs/react', () => ({ Head: () => null }));
vi.mock('@xyflow/react', () => ({
    Handle: () => null, Background: () => null, MiniMap: props => { observed.minimap = props; return <div>Minimapa</div>; },
    Position: { Left:'left', Right:'right', Top:'top', Bottom:'bottom' },
    ReactFlow: props => {
        observed.props = props;
        return <div>{props.nodes.map(node => { const Node = props.nodeTypes[node.type]; return <Node key={node.id} id={node.id} data={node.data} />; })}{props.children}</div>;
    },
}));
import Shared from '@/Pages/AI/MindMaps/Shared';
afterEach(cleanup);
const nodes = [{id:'r',position:{x:2,y:4},data:{label:'Objetivo',level:0}}, {id:'a',position:{x:500,y:300},data:{label:'Primeira entrega'}}, {id:'b',position:{x:850,y:900},data:{label:'Resultado final'}}];
const edges = [{id:'e1',source:'r',target:'a'}, {id:'e2',source:'a',target:'b'}];

describe('shared mind map reading', () => {
    it('restores all ordered topics after personalization and collapse', () => {
        const map = { title: 'Plano', nodes, edges };
        const before = JSON.stringify(map);
        render(<Shared sharedMap={map} />);
        const baseline = observed.props.nodes.map(node => ({ id: node.id, position: node.position }));
        fireEvent.click(screen.getByRole('button', { name: 'Personalizar disposição dos tópicos' }));
        act(() => observed.props.onNodesChange([{ id: 'a', type: 'position', position: { x: 1500, y: 1900 } }]));
        expect(observed.props.nodes.find(node => node.id === 'a').position).toEqual({ x: 1500, y: 1900 });
        fireEvent.click(screen.getByRole('button', { name: 'Recolher todos os tópicos' }));
        fireEvent.click(screen.getByRole('button', { name: 'Restaurar mapa original' }));
        expect(observed.props.nodes.map(node => ({ id: node.id, position: node.position }))).toEqual(baseline);
        expect(screen.getByRole('button', { name: 'Restaurar mapa original' }).getAttribute('aria-pressed')).toBe('true');
        expect(JSON.stringify(map)).toBe(before);
    });
    it('renders topic icons and images in cards and reading panel with correct geometry', () => {
        const illustrated = { ...nodes[0], data: { ...nodes[0].data, icon: 'rocket', image_url: '/storage/topic.png', image_alt: 'Lançamento' } };
        render(<Shared sharedMap={{ title: 'Plano', nodes: [illustrated], edges: [] }} />);
        expect(screen.getByLabelText('Ícone: rocket')).toBeTruthy();
        expect(screen.getByAltText('Lançamento').getAttribute('src')).toBe('/storage/topic.png');
        expect(observed.props.nodes[0].height).toBe(160);
        fireEvent.click(screen.getByRole('button', { name: 'Ampliar imagem de Objetivo' }));
        expect(screen.getAllByAltText('Lançamento')).toHaveLength(2);
    });
    it('keeps short cards compact without a topic footer or redundant read button', () => {
        render(<Shared sharedMap={{ title: 'Plano', nodes, edges }} />);
        expect(screen.queryByText('Tópico')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Ler' })).toBeNull();
        expect(observed.props.nodes.every(node => node.height === 64)).toBe(true);
    });
    it('does not render unsafe image URLs', () => {
        render(<Shared sharedMap={{ title: 'Plano', nodes: [{ ...nodes[0], data: { label: 'Teste', image_url: 'javascript:alert(1)' } }], edges: [] }} />);
        expect(screen.queryByRole('img')).toBeNull();
    });

    it('collapses branches locally without allowing content edits', () => {
        const map = {title:'Plano',nodes,edges};const before=JSON.stringify(map);
        render(<Shared sharedMap={map} />);
        expect(observed.props.nodes).toHaveLength(3);
        fireEvent.click(screen.getByRole('button',{name:'Recolher subtópicos de Objetivo'}));
        expect(observed.props.nodes).toHaveLength(1);
        fireEvent.click(screen.getByRole('button',{name:'Expandir subtópicos de Objetivo'}));
        expect(observed.props.nodes).toHaveLength(3);
        fireEvent.click(screen.getByRole('button',{name:'Recolher todos os tópicos'}));
        expect(observed.props.nodes).toHaveLength(1);
        fireEvent.click(screen.getByRole('button',{name:'Expandir todos os tópicos'}));
        expect(observed.props.nodes).toHaveLength(3);
        expect(observed.props.nodesDraggable).toBe(true);
        expect(observed.props.nodesConnectable).toBe(false);
        expect(observed.props.deleteKeyCode).toBeNull();
        expect(JSON.stringify(map)).toBe(before);
    });
    it('shows the complete long topic in the reading panel and closes with Escape', () => {
        const long = 'Texto longo com detalhes. '.repeat(200);
        render(<Shared sharedMap={{title:'Plano',nodes:[{...nodes[0],data:{label:long}}],edges:[]}} />);
        fireEvent.click(screen.getByRole('button',{name:'Ler'}));
        const panel=screen.getByRole('dialog',{name:'Leitura do tópico'});
        expect(panel.textContent).toContain(long);
        fireEvent.keyDown(window,{key:'Escape'});
        expect(screen.queryByRole('dialog',{name:'Leitura do tópico'})).toBeNull();
    });
    it('allows original positions and minimap without mutating source coordinates', () => {
        render(<Shared sharedMap={{title:'Plano',nodes,edges}} />);
        fireEvent.click(screen.getByRole('button',{name:'Restaurar mapa original'}));
        expect(observed.props.nodes[1].position).toEqual(readingTree(nodes, edges).positions.get('a'));
        fireEvent.click(screen.getByRole('button',{name:'Ocultar minimapa'}));
        fireEvent.click(screen.getByRole('button',{name:'Mostrar minimapa'}));
        expect(screen.getByText('Minimapa')).toBeTruthy();
    });
    it('keeps sibling branches and moved cards unchanged when a single branch is hidden', () => {
        const sibling = {id:'s',position:{x:500,y:600},data:{label:'Outro ramo'}};
        const map = {title:'Plano',nodes:[...nodes,sibling],edges:[...edges,{id:'e3',source:'r',target:'s'}]};
        const before = JSON.stringify(map);
        render(<Shared sharedMap={map} />);
        const siblingBefore = observed.props.nodes.find(node => node.id === 's').position;
        act(() => observed.props.onNodesChange([{id:'b',type:'position',position:{x:990,y:700}}]));
        fireEvent.click(screen.getByRole('button',{name:'Recolher subtópicos de Primeira entrega'}));
        expect(observed.props.nodes.map(node => node.id)).toEqual(['r','a','s']);
        expect(observed.props.nodes.find(node => node.id === 's').position).toEqual(siblingBefore);
        fireEvent.click(screen.getByRole('button',{name:'Expandir subtópicos de Primeira entrega'}));
        expect(observed.props.nodes.find(node => node.id === 'b').position).toEqual({x:990,y:700});
        expect(JSON.stringify(map)).toBe(before);
    });
    it('provides minimap geometry and centers the viewport on minimap interactions', () => {
        render(<Shared sharedMap={{title:'Plano',nodes,edges}} />);
        const flow = {setCenter:vi.fn(), getZoom:()=>0.75};
        window.matchMedia = vi.fn(() => ({matches:false}));
        act(() => observed.props.onInit(flow));
        expect(observed.props.nodes.every(node => node.width === 260 && node.height === 64)).toBe(true);
        expect(observed.minimap.pannable).toBe(true);
        expect(observed.minimap.zoomable).toBe(true);
        act(() => observed.minimap.onClick({}, {x:400,y:500}));
        expect(flow.setCenter).toHaveBeenLastCalledWith(400,500,{zoom:0.75,duration:0});
        act(() => observed.minimap.onNodeClick({}, observed.props.nodes[0]));
        expect(flow.setCenter).toHaveBeenLastCalledWith(observed.props.nodes[0].position.x+130,observed.props.nodes[0].position.y+32,{zoom:0.75,duration:0});
    });
    it('handles cycles, disconnected nodes and cross references without hiding unrelated roots', () => {
        const graph=[...nodes,{id:'x',data:{label:'Outra iniciativa'}}];
        const tree=readingTree(graph,[...edges,{source:'b',target:'r'},{source:'a',target:'x',data:{kind:'relationship'}}]);
        expect(tree.positions.size).toBe(4);
        expect([...visibleReadingNodes(tree,new Set(['r']))]).toEqual(['r','x']);
        expect(tree.positions.get('a').x).toBeGreaterThan(tree.positions.get('r').x);
    });
});
