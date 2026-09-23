import AdminLayout from '../layout';
export default function Redemptions({ redemptions }: any) {
    return (
        <AdminLayout title="Canjes">
            <div className="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Partner</th>
                            <th>Lugar</th>
                            <th>Beneficio</th>
                            <th>Estado</th>
                            <th>Ahorro</th>
                        </tr>
                    </thead>
                    <tbody>
                        {redemptions.data.map((r: any) => (
                            <tr key={r.id}>
                                <td>{r.created_at}</td>
                                <td>{r.user?.name || r.user?.email}</td>
                                <td>{r.partner_name}</td>
                                <td>{r.location_name}</td>
                                <td>{r.benefit_title}</td>
                                <td>{r.status}</td>
                                <td>{r.savings_amount}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
