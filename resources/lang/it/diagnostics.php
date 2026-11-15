<?php

return [
  'title' => 'Rapporto diagnostico',
  'help' => 'Prepara un rapporto limitato sull’installazione da esaminare o scaricare. Nessun dato viene inviato a un fornitore di assistenza. I rapporti scadono dopo 30 minuti.',
  'categories' => 'Includi nel rapporto',
  'category' => [
    'system_summary' => 'Versioni del sistema e dell’ambiente di esecuzione',
    'update_run' => 'Esecuzione dell’aggiornamento selezionata o più recente',
    'plugin_health' => 'Versioni e stato di attivazione dei plugin installati',
    'saved_health' => 'Conteggi salvati dei controlli di stato',
    'recent_application_errors' => 'Timestamp e impronte degli errori recenti',
  ],
  'errors_help' => 'La diagnostica degli errori contiene solo timestamp, livelli di gravità e impronte. Sono esclusi messaggi, dati delle richieste, credenziali, percorsi, contenuti e log completi. Gli altri canali di log non vengono raccolti.',
  'consent' => 'Prepara le informazioni diagnostiche selezionate perché io possa esaminarle.',
  'prepare' => 'Prepara il rapporto diagnostico',
  'review' => 'Esamina qui sotto il rapporto così come verrà scaricato. Per rimuovere categorie, cambia la selezione e prepara un nuovo rapporto. Il download non raccoglie nuovi dati.',
  'download' => 'Scarica il rapporto esaminato',
  'expired' => 'Il rapporto è scaduto o appartiene a un altro utente. Prepara un nuovo rapporto.',
];
