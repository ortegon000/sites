---
paths:
  - 'app/{Models/Charge.php,Models/ChargePayment.php,Actions/Charges/**}'
---

# Charges

## El estatus de un cobro se deriva de sus abonos
Nunca escribas `charges.status` a mano. Un `Charge` tiene `payments()` (`ChargePayment`) y `syncStatusFromPayments()` deriva estatus y `paid_at`: pagado si los abonos cubren el monto, vencido si pasó la fecha con saldo, parcial si hay algo abonado, pendiente si no.

Toda acción que toque montos o abonos (registrar, borrar, editar el cobro) debe llamar a `syncStatusFromPayments()` al final. "Marcar pagado" registra el restante como un abono en vez de cambiar el estatus, para que un cobro pagado siempre tenga con qué respaldarse y el restante nunca contradiga a la insignia.

Al sumar dinero pendiente en consultas usa el saldo (`selectRemainingTotals()` en `Charge`), no `sum(amount)`: un cobro abonado a la mitad no debe pesar completo.
