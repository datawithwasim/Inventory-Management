import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import Layout from './components/Layout';
import { useSession } from './lib/session';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import CustomerList from './pages/customers/List';
import CustomerDetail from './pages/customers/Detail';
import CustomerForm from './pages/customers/Form';
import SupplierList from './pages/suppliers/List';
import SupplierDetail from './pages/suppliers/Detail';
import SupplierForm from './pages/suppliers/Form';
import SupplierItemList from './pages/supplierItems/List';
import SupplierItemDetail from './pages/supplierItems/Detail';
import SupplierItemForm from './pages/supplierItems/Form';
import ItemList from './pages/items/List';
import ItemDetail from './pages/items/Detail';
import ItemForm from './pages/items/Form';
import PoList from './pages/purchase/PoList';
import PoDetail from './pages/purchase/PoDetail';
import PoForm from './pages/purchase/PoForm';
import GrnList from './pages/purchase/GrnList';
import GrnDetail from './pages/purchase/GrnDetail';
import GrnForm from './pages/purchase/GrnForm';
import BillList from './pages/purchase/BillList';
import BillDetail from './pages/purchase/BillDetail';
import BillForm from './pages/purchase/BillForm';
import { ReqList, ReqForm, ReqDetail } from './pages/purchase/ReqPages';
import { ReturnList, ReturnCreate, ReturnDetail } from './pages/purchase/ReturnPages';
import { legacyUrl } from './lib/api';

function Protected() {
  const { session } = useSession();
  const loc = useLocation();
  if (!session?.authenticated) return <Navigate to="/login" state={{ from: loc.pathname }} replace />;
  return <Layout />;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Protected />}>
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/customers" element={<CustomerList />} />
        <Route path="/customers/create" element={<CustomerForm />} />
        <Route path="/customers/:id" element={<CustomerDetail />} />
        <Route path="/customers/:id/edit" element={<CustomerForm />} />
        <Route path="/suppliers" element={<SupplierList />} />
        <Route path="/suppliers/create" element={<SupplierForm />} />
        <Route path="/suppliers/:id" element={<SupplierDetail />} />
        <Route path="/suppliers/:id/edit" element={<SupplierForm />} />
        <Route path="/purchase/supplier-items" element={<SupplierItemList />} />
        <Route path="/purchase/supplier-items/create" element={<SupplierItemForm />} />
        <Route path="/purchase/supplier-items/:id" element={<SupplierItemDetail />} />
        <Route path="/purchase/supplier-items/:id/edit" element={<SupplierItemForm />} />
        <Route path="/items" element={<ItemList />} />
        <Route path="/items/create" element={<ItemForm />} />
        <Route path="/items/:id" element={<ItemDetail />} />
        <Route path="/items/:id/edit" element={<ItemForm />} />
        <Route path="/purchase/orders" element={<PoList />} />
        <Route path="/purchase/orders/create" element={<PoForm />} />
        <Route path="/purchase/orders/:id" element={<PoDetail />} />
        <Route path="/purchase/orders/:id/edit" element={<PoForm />} />
        <Route path="/purchase/orders/:id/receive" element={<GrnForm />} />
        <Route path="/purchase/grns" element={<GrnList />} />
        <Route path="/purchase/grns/create" element={<GrnForm />} />
        <Route path="/purchase/grns/:id" element={<GrnDetail />} />
        <Route path="/purchase/bills" element={<BillList />} />
        <Route path="/purchase/bills/:id" element={<BillDetail />} />
        <Route path="/purchase/bills/:id/edit" element={<BillForm />} />
        <Route path="/purchase/requisitions" element={<ReqList />} />
        <Route path="/purchase/requisitions/create" element={<ReqForm />} />
        <Route path="/purchase/requisitions/:id" element={<ReqDetail />} />
        <Route path="/purchase/returns" element={<ReturnList />} />
        <Route path="/purchase/returns/create" element={<ReturnCreate />} />
        <Route path="/purchase/returns/:id" element={<ReturnDetail />} />
        <Route path="*" element={<NotBuilt />} />
      </Route>
    </Routes>
  );
}

/** A path with no React screen yet: hand over to the PHP page. */
function NotBuilt() {
  const loc = useLocation();
  window.location.replace(legacyUrl(loc.pathname + loc.search));
  return <div className="text-muted p-4">Opening…</div>;
}
