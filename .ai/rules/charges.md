---
paths:
  - 'app/{Models/Charge.php,Models/ChargePayment.php,Actions/Charges/**}'
  - 'app/{Models/Charge.php,Models/ChargePayment.php,Actions/Charges/**,Livewire/ChargesPanel.php}'
---

# Charges

## El estatus de un cobro se deriva de sus abonos
Nunca escribas `charges.status` a mano. Un `Charge` tiene `payments()` (`ChargePayment`) y `syncStatusFromPayments()` deriva estatus y `paid_at`: pagado si los abonos cubren el monto, vencido si pasó la fecha con saldo, parcial si hay algo abonado, pendiente si no.

Toda acción que toque montos o abonos (registrar, borrar, editar el cobro) debe llamar a `syncStatusFromPayments()` al final. "Marcar pagado" registra el restante como un abono en vez de cambiar el estatus, para que un cobro pagado siempre tenga con qué respaldarse y el restante nunca contradiga a la insignia.

Al sumar dinero pendiente en consultas usa el saldo (`selectRemainingTotals()` en `Charge`), no `sum(amount)`: un cobro abonado a la mitad no debe pesar completo.

## Un cobro pagado no pierde sus abonos
Si un cobro está en `Pagado`, no se puede eliminar ninguno de sus abonos: el panel (`ChargesPanel::deletePayment`) lo rechaza con un aviso y la vista oculta el botón de borrar. Un cobro pagado siempre debe tener con qué respaldarse. Para corregir un error hay que regresarlo primero a pendiente (ver la regla siguiente); solo un cobro parcial o pendiente admite borrar abonos uno a uno.

## Un cobro pagado no se edita: se regresa a pendiente
Editar un cobro en `Pagado` está bloqueado (`ChargesPanel::rejectIfPaid`). La única salida es `updateChargeStatus(..., 'pendiente')`, que corre `ReopenCharge`: borra todos los abonos y deriva de nuevo el estatus (pendiente, o vencido si ya pasó la fecha). El estatus a mano solo admite `pagado` (registra el restante como abono con `MarkChargeAsPaid`) y `pendiente`; parcial y vencido nunca se eligen. Sustituye la nota anterior de "corregir editando el monto": en un cobro pagado ya no se puede editar.
