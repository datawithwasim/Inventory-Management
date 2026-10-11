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
import { WarehouseList, WarehouseDetail, WarehouseForm } from './pages/stock/WarehousePages';
import { LocationList, LocationDetail, LocationForm } from './pages/stock/LocationPages';
import { StockOverview, StockRacks, StockBatches, StockBatch, StockLedger } from './pages/stock/StockViews';
import { AdjustmentList, AdjustmentForm, AdjustmentDetail, TransferList, TransferForm, TransferDetail, TakeList, TakeForm, TakeDetail } from './pages/stock/DocPages';
import { OrderList, OrderForm, OrderDetail } from './pages/sales/OrderPages';
import { DeliveryList, DeliveryForm, DeliveryDetail } from './pages/sales/DeliveryPages';
import { InvoiceList, InvoiceDetail, InvoiceForm } from './pages/sales/InvoicePages';
import { SalesReturnList, SalesReturnCreate, SalesReturnDetail } from './pages/sales/ReturnPages';
import { ReportIndex, ReportShow } from './pages/reports/Reports';
import { SettingsLayout, SettingsHome, CompanyTab, PreferencesTab, NumberingTab, AppearanceTab, ModulesTab, LabelsTab, WorkflowTab, TemplatesTab } from './pages/settings/Settings';
import { UserList, UserForm, RoleList, RoleForm } from './pages/settings/Team';
import { MasterList, MasterForm } from './pages/settings/Masters';
import Profile from './pages/settings/Profile';
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
        <Route path="/warehouses" element={<WarehouseList />} />
        <Route path="/warehouses/create" element={<WarehouseForm />} />
        <Route path="/warehouses/:id" element={<WarehouseDetail />} />
        <Route path="/warehouses/:id/edit" element={<WarehouseForm />} />
        <Route path="/locations" element={<LocationList />} />
        <Route path="/locations/create" element={<LocationForm />} />
        <Route path="/locations/:id" element={<LocationDetail />} />
        <Route path="/locations/:id/edit" element={<LocationForm />} />
        <Route path="/stock" element={<StockOverview />} />
        <Route path="/stock/racks" element={<StockRacks />} />
        <Route path="/stock/batches" element={<StockBatches />} />
        <Route path="/stock/batches/:id" element={<StockBatch />} />
        <Route path="/stock/ledger" element={<StockLedger />} />
        <Route path="/stock/adjustments" element={<AdjustmentList />} />
        <Route path="/stock/adjustments/create" element={<AdjustmentForm />} />
        <Route path="/stock/adjustments/:id" element={<AdjustmentDetail />} />
        <Route path="/stock/transfers" element={<TransferList />} />
        <Route path="/stock/transfers/create" element={<TransferForm />} />
        <Route path="/stock/transfers/:id" element={<TransferDetail />} />
        <Route path="/stock/takes" element={<TakeList />} />
        <Route path="/stock/takes/create" element={<TakeForm />} />
        <Route path="/stock/takes/:id" element={<TakeDetail />} />
        <Route path="/sales/orders" element={<OrderList />} />
        <Route path="/sales/orders/create" element={<OrderForm />} />
        <Route path="/sales/orders/:id" element={<OrderDetail />} />
        <Route path="/sales/orders/:id/edit" element={<OrderForm />} />
        <Route path="/sales/orders/:id/deliver" element={<DeliveryForm />} />
        <Route path="/sales/deliveries" element={<DeliveryList />} />
        <Route path="/sales/deliveries/create" element={<DeliveryForm />} />
        <Route path="/sales/deliveries/:id" element={<DeliveryDetail />} />
        <Route path="/sales/invoices" element={<InvoiceList />} />
        <Route path="/sales/invoices/:id" element={<InvoiceDetail />} />
        <Route path="/sales/invoices/:id/edit" element={<InvoiceForm />} />
        <Route path="/sales/returns" element={<SalesReturnList />} />
        <Route path="/sales/returns/create" element={<SalesReturnCreate />} />
        <Route path="/sales/returns/:id" element={<SalesReturnDetail />} />
        <Route path="/reports" element={<ReportIndex />} />
        <Route path="/reports/:slug" element={<ReportShow />} />
        <Route path="/settings" element={<SettingsLayout />}>
          <Route index element={<SettingsHome />} />
          <Route path="company" element={<CompanyTab />} />
          <Route path="preferences" element={<PreferencesTab />} />
          <Route path="numbering" element={<NumberingTab />} />
          <Route path="appearance" element={<AppearanceTab />} />
          <Route path="modules" element={<ModulesTab />} />
          <Route path="labels" element={<LabelsTab />} />
          <Route path="templates" element={<TemplatesTab />} />
          <Route path="workflow" element={<WorkflowTab />} />
          <Route path="*" element={<NotBuilt />} />
        </Route>
        <Route path="/users" element={<UserList />} />
        <Route path="/users/create" element={<UserForm />} />
        <Route path="/users/:id/edit" element={<UserForm />} />
        <Route path="/roles" element={<RoleList />} />
        <Route path="/roles/create" element={<RoleForm />} />
        <Route path="/roles/:id/edit" element={<RoleForm />} />
        <Route path="/masters" element={<Navigate to="/masters/categories" replace />} />
        <Route path="/masters/:type" element={<MasterList />} />
        <Route path="/masters/:type/create" element={<MasterForm />} />
        <Route path="/masters/:type/:id/edit" element={<MasterForm />} />
        <Route path="/profile" element={<Profile />} />
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
