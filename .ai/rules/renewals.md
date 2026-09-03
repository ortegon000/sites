---
paths:
  - 'app/{Actions/Renewals/**,Models/Renewal.php,Notifications/RenewalNoticeNotification.php}'
---

# Renewals

## El ciclo de renovación vive en `renewals`, una fila por vencimiento
Lo que caduca (Domain, License, Service anual) abre un `Renewal` con morph `renewable`: una fila por fecha de vencimiento, no un par de columnas en el activo, para que quede historial año con año. `OpenRenewalCycles` corre a diario y es idempotente por la llave única `(renewable_type, renewable_id, due_date)`: no insertes ciclos a mano.

Al renovar, un dominio o licencia genera la línea cobrable y empuja su fecha un año; un servicio anual NO genera línea, porque ya se cobra por su `next_charge_date` y duplicarla cobraría dos veces lo mismo.

Los correos al cliente (`RenewalNoticeNotification`) llevan enlace al portal y jamás credenciales en el cuerpo: un correo se queda en la bandeja, se reenvía y se filtra. Si el cliente no tiene contacto con correo, el ciclo se queda en `por_avisar` en vez de marcarse avisado.
