import { createRoot } from 'react-dom/client';

function NavigationBar() {
    return (
        <ul>
            <li>Something</li>
        </ul>
    );
}

const domNode = document.getElementById('mainNav');
const root = createRoot(domNode);
root.render(<NavigationBar />);