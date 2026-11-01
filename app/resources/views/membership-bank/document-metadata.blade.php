@if($metadata)
<div class="card">
<p>Datos leídos del PDF: {{$metadata['bank']}} · Cuenta {{$metadata['account']}} · {{$metadata['currency']}}</p>
<p>Período del documento: {{$metadata['period_start']}} — {{$metadata['period_end']}} · Emitido: {{$metadata['generated_at']}} (hora de Bolivia)</p>
<p>Saldo anterior: {{$metadata['opening_balance']}} · Saldo final: {{$metadata['closing_balance']}} · {{$metadata['debit_count']}} débitos · {{$metadata['credit_count']}} créditos. Saldos y cantidades verificados.</p>
<p>Compruebe que la cuenta leída corresponde al identificador de cuenta declarado.</p>
</div>
@endif
