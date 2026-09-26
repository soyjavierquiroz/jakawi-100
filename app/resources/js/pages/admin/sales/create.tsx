import SaleCreate from '../../promoter/sales/create';
export default function AdminSaleCreate(props: any) { return <SaleCreate {...props} endpoint="/admin/sales" back="/admin/sales" />; }
