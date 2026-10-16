import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import EmptyState from '@/Components/EmptyState';
import Skeleton from '@/Components/ui/Skeleton';
import { Button } from '@/Components/ui';

// ─── EmptyState ─────────────────────────────────────────────────────────────

describe('EmptyState', () => {
    it('renders title', () => {
        render(<EmptyState title="Nothing here yet" />);
        expect(screen.getByText('Nothing here yet')).toBeInTheDocument();
    });

    it('renders description when provided', () => {
        render(<EmptyState title="Empty" description="Get started by adding something." />);
        expect(screen.getByText(/Get started/)).toBeInTheDocument();
    });

    it('renders an action button', () => {
        const onClick = vi.fn();
        render(<EmptyState title="Empty" action={{ label: 'Add', onClick }} />);
        expect(screen.getByRole('button', { name: 'Add' })).toBeInTheDocument();
    });

    it('calls action onClick handler', async () => {
        const onClick = vi.fn();
        const { getByRole } = render(<EmptyState title="Test" action={{ label: 'Go', onClick }} />);
        getByRole('button', { name: 'Go' }).click();
        expect(onClick).toHaveBeenCalledOnce();
    });

    it('renders a link action when href is provided', () => {
        render(<EmptyState title="Test" action={{ label: 'View', href: '/somewhere' }} />);
        const link = screen.getByRole('link', { name: 'View' });
        expect(link).toHaveAttribute('href', '/somewhere');
    });

    it('renders icon slot', () => {
        const Icon = () => <svg data-testid="icon" />;
        render(<EmptyState title="Test" icon={<Icon />} />);
        expect(screen.getByTestId('icon')).toBeInTheDocument();
    });
});

// ─── Skeleton ───────────────────────────────────────────────────────────────

describe('Skeleton', () => {
    it('renders a rect by default', () => {
        const { container } = render(<Skeleton className="h-4 w-48" />);
        expect(container.firstChild).toHaveClass('animate-pulse');
    });

    it('renders circle variant with rounded-full', () => {
        const { container } = render(<Skeleton variant="circle" className="h-8 w-8" />);
        expect(container.firstChild).toHaveClass('rounded-full');
    });

    it('renders multiple text lines', () => {
        const { container } = render(<Skeleton variant="text" lines={3} />);
        expect(container.querySelectorAll('.animate-pulse')).toHaveLength(3);
    });
});

// ─── Button ──────────────────────────────────────────────────────────────────

describe('Button', () => {
    it('renders children', () => {
        render(<Button>Click me</Button>);
        expect(screen.getByRole('button', { name: 'Click me' })).toBeInTheDocument();
    });

    it('is disabled when disabled prop is set', () => {
        render(<Button disabled>Save</Button>);
        expect(screen.getByRole('button')).toBeDisabled();
    });
});
