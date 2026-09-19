---
paths:
  - 'app/{Actions/Renewals/**,Models/Renewal.php,Notifications/RenewalNoticeNotification.php,Console/Commands/ProcessScheduledCharges.php}'
---

# Commands

## El aviso de renovación al cliente es manual por ahora
`charges:process` abre los ciclos de renovación pero NO manda el aviso al cliente mientras `company.renewal_notices_automatic` (env `RENEWAL_NOTICES_AUTOMATIC`, por defecto false) esté apagado: los ciclos quedan en `por_avisar` hasta que alguien pulse "Avisar al cliente" en Renovaciones (`NotifyClientOfRenewal`). Los correos internos al equipo (cobros, dominios, licencias mensuales) no dependen de este interruptor. No prendas el envío automático sin que el usuario lo pida, porque el correo pide depositar a una cuenta y hoy sus datos son de prueba.
