<?php
declare(strict_types=1);
return json_decode(<<<'JSON'
{
  "Voir mon évaluation": {
    "en": "View my assessment",
    "de": "Meine Bewertung ansehen",
    "it": "Vedi la mia valutazione",
    "es": "Ver mi evaluación"
  },
  "Partager ce contenu": {
    "en": "Share this content",
    "de": "Diesen Inhalt teilen",
    "it": "Condividi questo contenuto",
    "es": "Compartir este contenido"
  },
  "Contenu partagé": {
    "en": "Shared content",
    "de": "Gemeinsamer Inhalt",
    "it": "Contenuto condiviso",
    "es": "Contenido compartido"
  },
  "Copie indépendante": {
    "en": "Independent copy",
    "de": "Unabhängige Kopie",
    "it": "Copia indipendente",
    "es": "Copia independiente"
  },
  "Utilisation externe": {
    "en": "External use",
    "de": "Externe Nutzung",
    "it": "Uso esterno",
    "es": "Uso externo"
  },
  "Lien à partager": {
    "en": "Sharing link",
    "de": "Freigabelink",
    "it": "Link da condividere",
    "es": "Enlace para compartir"
  },
  "Ouvrir le lien": {
    "en": "Open link",
    "de": "Link öffnen",
    "it": "Apri il link",
    "es": "Abrir enlace"
  },
  "Partage non autorisé.": {
    "en": "Sharing is not allowed.",
    "de": "Freigabe nicht erlaubt.",
    "it": "Condivisione non autorizzata.",
    "es": "No se permite compartir."
  },
  "Enregistrez la page avec le statut Prêt avant de la partager.": {
    "en": "Save the page as Ready before sharing it.",
    "de": "Speichern Sie die Seite als Bereit, bevor Sie sie teilen.",
    "it": "Salva la pagina come Pronta prima di condividerla.",
    "es": "Guarde la página como Lista antes de compartirla."
  },
  "Ce lien de partage est indisponible.": {
    "en": "This sharing link is unavailable.",
    "de": "Dieser Freigabelink ist nicht verfügbar.",
    "it": "Questo link di condivisione non è disponibile.",
    "es": "Este enlace no está disponible."
  },
  "Un compte enseignant est nécessaire.": {
    "en": "A teacher account is required.",
    "de": "Ein Lehrpersonenkonto ist erforderlich.",
    "it": "È necessario un account docente.",
    "es": "Se necesita una cuenta docente."
  },
  "Ce lien ne permet pas de récupérer une page.": {
    "en": "This link does not allow importing a page.",
    "de": "Dieser Link erlaubt keinen Seitenimport.",
    "it": "Questo link non consente di importare una pagina.",
    "es": "Este enlace no permite importar una página."
  },
  "Contenu ajouté à votre bibliothèque.": {
    "en": "Content added to your library.",
    "de": "Inhalt zu Ihrer Bibliothek hinzugefügt.",
    "it": "Contenuto aggiunto alla tua biblioteca.",
    "es": "Contenido añadido a su biblioteca."
  },
  "Contenu indisponible": {
    "en": "Content unavailable",
    "de": "Inhalt nicht verfügbar",
    "it": "Contenuto non disponibile",
    "es": "Contenido no disponible"
  },
  "Partagé par :name": {
    "en": "Shared by :name",
    "de": "Geteilt von :name",
    "it": "Condiviso da :name",
    "es": "Compartido por :name"
  },
  "La remise de travaux nécessite une inscription dans un parcours.": {
    "en": "Submitting work requires enrollment in a pathway.",
    "de": "Für die Abgabe von Arbeiten ist die Einschreibung in einen Lernpfad erforderlich.",
    "it": "Per consegnare lavori è necessaria l’iscrizione a un percorso.",
    "es": "Para entregar trabajos debe inscribirse en un itinerario."
  },
  "Destination": {
    "en": "Destination",
    "de": "Ziel",
    "it": "Destinazione",
    "es": "Destino"
  },
  "Ma bibliothèque uniquement": {
    "en": "My library only",
    "de": "Nur meine Bibliothek",
    "it": "Solo la mia biblioteca",
    "es": "Solo mi biblioteca"
  },
  "Récupérer ce contenu": {
    "en": "Import this content",
    "de": "Diesen Inhalt übernehmen",
    "it": "Importa questo contenuto",
    "es": "Importar este contenido"
  },
  "Se connecter pour récupérer ce contenu": {
    "en": "Sign in to import this content",
    "de": "Anmelden, um diesen Inhalt zu übernehmen",
    "it": "Accedi per importare questo contenuto",
    "es": "Iniciar sesión para importar este contenido"
  },
  "Télécharger la page JSON": {
    "en": "Download page as JSON",
    "de": "Seite als JSON herunterladen",
    "it": "Scarica la pagina JSON",
    "es": "Descargar la página JSON"
  },
  "Les collègues connectés à cette plateforme utilisent la même page. Les modifications du contenu sont communes à tous les parcours qui l’utilisent.": {
    "en": "Colleagues signed in to this platform use the same page. Content changes apply to all pathways using it.",
    "de": "Auf dieser Plattform angemeldete Kolleginnen und Kollegen nutzen dieselbe Seite. Änderungen gelten für alle Lernpfade, die sie verwenden.",
    "it": "I colleghi connessi a questa piattaforma usano la stessa pagina. Le modifiche si applicano a tutti i percorsi che la utilizzano.",
    "es": "Los colegas conectados a esta plataforma usan la misma página. Los cambios se aplican a todos los itinerarios que la utilizan."
  },
  "Chaque collègue récupère sa propre copie. Les modifications ultérieures restent indépendantes. Un fichier JSON permet aussi de la transférer vers une autre installation liike.": {
    "en": "Each colleague gets their own copy. Later changes remain independent. A JSON file also allows transfer to another liike installation.",
    "de": "Jede Lehrperson erhält eine eigene Kopie. Spätere Änderungen bleiben unabhängig. Eine JSON-Datei ermöglicht auch die Übertragung in eine andere liike-Installation.",
    "it": "Ogni collega riceve la propria copia. Le modifiche successive restano indipendenti. Un file JSON consente il trasferimento a un’altra installazione liike.",
    "es": "Cada colega recibe su propia copia. Los cambios posteriores son independientes. Un archivo JSON permite transferirla a otra instalación de liike."
  },
  "Une page ajoutée au parcours commence en consultation simple. Vous pouvez ensuite régler son type et ses échéances.": {
    "en": "A page added to a pathway starts in read-only consultation mode. You can then set its type and deadlines.",
    "de": "Eine dem Lernpfad hinzugefügte Seite beginnt als einfache Lektüre. Anschliessend können Sie Typ und Fristen festlegen.",
    "it": "Una pagina aggiunta al percorso inizia come consultazione semplice. Puoi poi impostarne il tipo e le scadenze.",
    "es": "Una página añadida al itinerario comienza en consulta simple. Después puede ajustar el tipo y los plazos."
  },
  "Vue synthétique du parcours": {
    "en": "Pathway overview",
    "de": "Lernpfadübersicht",
    "it": "Sintesi del percorso",
    "es": "Resumen del itinerario"
  },
  "Choisir le format d’export": {
    "en": "Choose export format",
    "de": "Exportformat wählen",
    "it": "Scegli il formato di esportazione",
    "es": "Elegir el formato de exportación"
  },
  "Un seul lien. Vous choisissez les modes autorisés ; votre collègue choisit comment utiliser le contenu.": {
    "en": "One link. You choose the allowed modes; your colleague chooses how to use the content.",
    "de": "Ein Link. Sie erlauben die Nutzungsarten; Ihre Kolleginnen und Kollegen wählen, wie sie den Inhalt verwenden.",
    "it": "Un solo link. Scegli le modalità consentite; il collega sceglie come usare il contenuto.",
    "es": "Un solo enlace. Usted permite los modos; su colega elige cómo usar el contenido."
  },
  "Autoriser le partage": {
    "en": "Enable sharing",
    "de": "Freigabe aktivieren",
    "it": "Abilita la condivisione",
    "es": "Permitir compartir"
  },
  "Modes autorisés": {
    "en": "Allowed modes",
    "de": "Erlaubte Nutzungsarten",
    "it": "Modalità consentite",
    "es": "Modos permitidos"
  },
  "Enregistrer le partage": {
    "en": "Save sharing settings",
    "de": "Freigabe speichern",
    "it": "Salva la condivisione",
    "es": "Guardar la configuración"
  },
  "Réglages du partage enregistrés.": {
    "en": "Sharing settings saved.",
    "de": "Freigabeeinstellungen gespeichert.",
    "it": "Impostazioni di condivisione salvate.",
    "es": "Configuración guardada."
  },
  "Le lien reste identique après modification des modes ou réactivation. Les nouvelles utilisations suivent vos réglages ; les copies et les contenus déjà récupérés sont conservés. Désactiver l’utilisation externe ferme aussi les séances anonymes en cours.": {
    "en": "The link stays the same after changing modes or reactivating sharing. New uses follow your settings; existing copies and imports are kept. Disabling external use also closes active anonymous sessions.",
    "de": "Der Link bleibt nach Änderungen oder Reaktivierung gleich. Neue Nutzungen folgen Ihren Einstellungen; vorhandene Kopien und Importe bleiben erhalten. Das Deaktivieren der externen Nutzung beendet auch laufende anonyme Sitzungen.",
    "it": "Il link resta invariato dopo modifiche o riattivazione. I nuovi utilizzi seguono le impostazioni; copie e contenuti già importati restano disponibili. Disattivare l’uso esterno chiude anche le sessioni anonime in corso.",
    "es": "El enlace no cambia al modificar los modos o reactivarlo. Los nuevos usos siguen su configuración; se conservan las copias e importaciones existentes. Desactivar el uso externo también cierra las sesiones anónimas activas."
  },
  "Consultez le contenu sans inscription ou animez un QCM en classe. Les élèves rejoignent la séance par lien ou QR code, sans nom ni compte. Les réponses anonymes sont temporaires et ne deviennent pas des notes.": {
    "en": "View content without registration or host a classroom quiz. Students join via a link or QR code, without a name or account. Anonymous answers are temporary and are not recorded as grades.",
    "de": "Inhalte ohne Registrierung ansehen oder ein Klassenquiz leiten. Lernende treten per Link oder QR-Code bei, ohne Namen oder Konto. Anonyme Antworten sind vorübergehend und werden nicht zu Noten.",
    "it": "Consulta i contenuti senza registrazione o avvia un quiz in classe. Gli studenti partecipano tramite link o codice QR, senza nome né account. Le risposte anonime sono temporanee e non diventano voti.",
    "es": "Consulte el contenido sin registrarse o dirija un cuestionario en clase. El alumnado accede mediante enlace o código QR, sin nombre ni cuenta. Las respuestas anónimas son temporales y no se convierten en notas."
  },
  "Comment souhaitez-vous utiliser ce contenu ?": {
    "en": "How would you like to use this content?",
    "de": "Wie möchten Sie diesen Inhalt verwenden?",
    "it": "Come desideri usare questo contenuto?",
    "es": "¿Cómo desea utilizar este contenido?"
  },
  "Choisir ce mode": {
    "en": "Choose this mode",
    "de": "Diese Nutzungsart wählen",
    "it": "Scegli questa modalità",
    "es": "Elegir este modo"
  },
  "Choisir un autre mode": {
    "en": "Choose another mode",
    "de": "Andere Nutzungsart wählen",
    "it": "Scegli un’altra modalità",
    "es": "Elegir otro modo"
  },
  "Enseignant : se connecter pour animer une séance": {
    "en": "Teacher: sign in to host a session",
    "de": "Lehrperson: anmelden, um eine Sitzung zu leiten",
    "it": "Docente: accedi per avviare una sessione",
    "es": "Docente: inicie sesión para dirigir una sesión"
  },
  "Animer une séance anonyme": {
    "en": "Host an anonymous session",
    "de": "Anonyme Sitzung leiten",
    "it": "Avvia una sessione anonima",
    "es": "Dirigir una sesión anónima"
  },
  "Votre enseignant vous donnera le lien ou le QR code de la séance.": {
    "en": "Your teacher will give you the session link or QR code.",
    "de": "Ihre Lehrperson gibt Ihnen den Link oder QR-Code zur Sitzung.",
    "it": "Il docente fornirà il link o il codice QR della sessione.",
    "es": "Su docente le dará el enlace o el código QR de la sesión."
  },
  "Aucun nom, courriel ou compte n’est demandé. Un identifiant aléatoire sert uniquement à vos réponses dans cette séance.": {
    "en": "No name, email or account is required. A random identifier is used only for your answers in this session.",
    "de": "Es werden weder Name noch E-Mail oder Konto verlangt. Eine zufällige Kennung dient nur Ihren Antworten in dieser Sitzung.",
    "it": "Non sono richiesti nome, email o account. Un identificativo casuale serve solo alle risposte in questa sessione.",
    "es": "No se pide nombre, correo ni cuenta. Un identificador aleatorio se usa solo para sus respuestas en esta sesión."
  },
  "Rejoindre anonymement": {
    "en": "Join anonymously",
    "de": "Anonym teilnehmen",
    "it": "Partecipa anonimamente",
    "es": "Participar de forma anónima"
  },
  "Scannez le QR code ou ouvrez le lien pour rejoindre anonymement.": {
    "en": "Scan the QR code or open the link to join anonymously.",
    "de": "QR-Code scannen oder Link öffnen, um anonym teilzunehmen.",
    "it": "Scansiona il codice QR o apri il link per partecipare anonimamente.",
    "es": "Escanee el código QR o abra el enlace para participar de forma anónima."
  },
  "Réponses : :count / :total participants": {
    "en": "Answers: :count / :total participants",
    "de": "Antworten: :count / :total Teilnehmende",
    "it": "Risposte: :count / :total partecipanti",
    "es": "Respuestas: :count / :total participantes"
  },
  "Rejoignez la séance pour continuer.": {
    "en": "Join the session to continue.",
    "de": "Treten Sie der Sitzung bei, um fortzufahren.",
    "it": "Partecipa alla sessione per continuare.",
    "es": "Únase a la sesión para continuar."
  },
  "Participation anonyme": {
    "en": "Anonymous participation",
    "de": "Anonyme Teilnahme",
    "it": "Partecipazione anonima",
    "es": "Participación anónima"
  },
  "QR code pour rejoindre la séance": {
    "en": "QR code to join the session",
    "de": "QR-Code zur Teilnahme",
    "it": "Codice QR per partecipare",
    "es": "Código QR para participar"
  },
  "Inviter les élèves": {
    "en": "Invite students",
    "de": "Lernende einladen",
    "it": "Invita gli studenti",
    "es": "Invitar al alumnado"
  },
  "Lien de la séance": {
    "en": "Session link",
    "de": "Sitzungslink",
    "it": "Link della sessione",
    "es": "Enlace de la sesión"
  },
  "Lien valable 24 heures. Les réponses anonymes sont ensuite supprimées automatiquement.": {
    "en": "Link valid for 24 hours. Anonymous answers are then automatically deleted.",
    "de": "Link 24 Stunden gültig. Anonyme Antworten werden danach automatisch gelöscht.",
    "it": "Link valido 24 ore. Le risposte anonime vengono poi eliminate automaticamente.",
    "es": "Enlace válido durante 24 horas. Las respuestas anónimas se eliminan después automáticamente."
  },
  "Activez JavaScript pour participer à la séance.": {
    "en": "Enable JavaScript to join the session.",
    "de": "Aktivieren Sie JavaScript, um teilzunehmen.",
    "it": "Attiva JavaScript per partecipare.",
    "es": "Active JavaScript para participar."
  },
  "Connexion interrompue. Réessayez dans un instant.": {
    "en": "Connection interrupted. Please try again shortly.",
    "de": "Verbindung unterbrochen. Versuchen Sie es gleich erneut.",
    "it": "Connessione interrotta. Riprova tra poco.",
    "es": "Conexión interrumpida. Inténtelo de nuevo en un momento."
  },
  "Terminez une séance avant d’en créer une nouvelle.": {
    "en": "End a session before creating another.",
    "de": "Beenden Sie eine Sitzung, bevor Sie eine neue erstellen.",
    "it": "Termina una sessione prima di crearne un’altra.",
    "es": "Finalice una sesión antes de crear otra."
  },
  "Cette séance a atteint sa capacité.": {
    "en": "This session is full.",
    "de": "Diese Sitzung ist voll.",
    "it": "Questa sessione ha raggiunto la capacità massima.",
    "es": "Esta sesión está completa."
  },
  "Mode de partage invalide.": {
    "en": "Invalid sharing mode.",
    "de": "Ungültige Nutzungsart.",
    "it": "Modalità di condivisione non valida.",
    "es": "Modo de uso no válido."
  },
  "Choisissez au moins un mode de partage.": {
    "en": "Choose at least one sharing mode.",
    "de": "Wählen Sie mindestens eine Nutzungsart.",
    "it": "Scegli almeno una modalità di condivisione.",
    "es": "Elija al menos un modo de uso."
  },
  "Le partage a changé. Rechargez la page avant de réessayer.": {
    "en": "Sharing settings changed. Reload the page before trying again.",
    "de": "Die Freigabe wurde geändert. Laden Sie die Seite neu und versuchen Sie es erneut.",
    "it": "La condivisione è cambiata. Ricarica la pagina e riprova.",
    "es": "La configuración cambió. Recargue la página antes de volver a intentarlo."
  },
  "Ce mode de partage n’est plus autorisé.": {
    "en": "This sharing mode is no longer allowed.",
    "de": "Diese Nutzungsart ist nicht mehr erlaubt.",
    "it": "Questa modalità di condivisione non è più consentita.",
    "es": "Este modo de uso ya no está permitido."
  },
  "Utilisation anonyme": {
    "en": "Anonymous use",
    "de": "Anonyme Nutzung",
    "it": "Uso anonimo",
    "es": "Uso anónimo"
  },
  "Compris": {
    "en": "Understood",
    "de": "Verstanden",
    "it": "Ho capito",
    "es": "Entendido"
  },
  "Connexion enseignant nécessaire": {
    "en": "Teacher sign-in required",
    "de": "Anmeldung als Lehrperson erforderlich",
    "it": "Accesso docente necessario",
    "es": "Se requiere acceso docente"
  },
  "Connectez-vous avec un compte enseignant pour accéder à cette utilisation.": {
    "en": "Sign in with a teacher account to access this use.",
    "de": "Melden Sie sich mit einem Lehrpersonenkonto an, um diese Nutzung aufzurufen.",
    "it": "Accedi con un account docente per questa modalità di utilizzo.",
    "es": "Inicie sesión con una cuenta docente para acceder a este uso."
  },
  "Ce contenu n’est pas disponible en utilisation anonyme.": {
    "en": "This content is not available for anonymous use.",
    "de": "Dieser Inhalt ist nicht anonym nutzbar.",
    "it": "Questo contenuto non è disponibile per l’uso anonimo.",
    "es": "Este contenido no está disponible para uso anónimo."
  },
  "Enseignant : se connecter": {
    "en": "Teacher: sign in",
    "de": "Lehrperson: anmelden",
    "it": "Docente: accedi",
    "es": "Docente: iniciar sesión"
  },
  "Contenu partagé de :name": {
    "en": "Content shared by :name",
    "de": "Von :name geteilter Inhalt",
    "it": "Contenuto condiviso da :name",
    "es": "Contenido compartido por :name"
  },
  "Mon contenu utilisé par d’autres enseignants": {
    "en": "My content used by other teachers",
    "de": "Mein von anderen Lehrpersonen genutzter Inhalt",
    "it": "Il mio contenuto usato da altri docenti",
    "es": "Mi contenido utilizado por otros docentes"
  },
  "Copie indépendante autorisée": {
    "en": "Independent copying allowed",
    "de": "Unabhängige Kopie erlaubt",
    "it": "Copia indipendente consentita",
    "es": "Copia independiente permitida"
  },
  "Utilisation externe autorisée": {
    "en": "External use allowed",
    "de": "Externe Nutzung erlaubt",
    "it": "Uso esterno consentito",
    "es": "Uso externo permitido"
  },
  "Origine": {
    "en": "Origin",
    "de": "Herkunft",
    "it": "Origine",
    "es": "Origen"
  },
  "Voir tous les contenus": {
    "en": "View all content",
    "de": "Alle Inhalte anzeigen",
    "it": "Mostra tutti i contenuti",
    "es": "Ver todos los contenidos"
  },
  "Filtrer par enseignants": {
    "en": "Filter by teachers",
    "de": "Nach Lehrpersonen filtern",
    "it": "Filtra per docenti",
    "es": "Filtrar por docentes"
  },
  "(moi)": {
    "en": "(me)",
    "de": "(ich)",
    "it": "(io)",
    "es": "(yo)"
  },
  "Partage": {
    "en": "Sharing",
    "de": "Freigabe",
    "it": "Condivisione",
    "es": "Uso compartido"
  },
  "Tous les modes": {
    "en": "All modes",
    "de": "Alle Nutzungsarten",
    "it": "Tutte le modalità",
    "es": "Todos los modos"
  },
  "Filtrer par partage": {
    "en": "Filter by sharing",
    "de": "Nach Freigabe filtern",
    "it": "Filtra per condivisione",
    "es": "Filtrar por uso compartido"
  },
  "Mes contenus partagés": {
    "en": "My shared content",
    "de": "Meine geteilten Inhalte",
    "it": "I miei contenuti condivisi",
    "es": "Mis contenidos compartidos"
  },
  "Copies indépendantes effectuées": {
    "en": "Independent copies made",
    "de": "Erstellte unabhängige Kopien",
    "it": "Copie indipendenti effettuate",
    "es": "Copias independientes realizadas"
  },
  "Utilisations externes autorisées": {
    "en": "External uses allowed",
    "de": "Erlaubte externe Nutzungen",
    "it": "Utilizzi esterni consentiti",
    "es": "Usos externos permitidos"
  },
  "Les copies indépendantes font partie de vos contenus.": {
    "en": "Independent copies are part of your own content.",
    "de": "Unabhängige Kopien gehören zu Ihren eigenen Inhalten.",
    "it": "Le copie indipendenti fanno parte dei tuoi contenuti.",
    "es": "Las copias independientes forman parte de sus contenidos."
  },
  "Repères de partage": {
    "en": "Sharing indicators",
    "de": "Freigabekennzeichnungen",
    "it": "Indicatori di condivisione",
    "es": "Indicadores de uso compartido"
  },
  "Utilisé par des collègues": {
    "en": "Used by colleagues",
    "de": "Von Kolleginnen und Kollegen genutzt",
    "it": "Usato da colleghi",
    "es": "Utilizado por colegas"
  },
  "Contenu d’un autre enseignant": {
    "en": "Content owned by another teacher",
    "de": "Inhalt einer anderen Lehrperson",
    "it": "Contenuto di un altro docente",
    "es": "Contenido de otro docente"
  },
  "Propriétaire : :name": {
    "en": "Owner: :name",
    "de": "Eigentümer: :name",
    "it": "Proprietario: :name",
    "es": "Propietario: :name"
  },
  "Copie indépendante de :name": {
    "en": "Independent copy from :name",
    "de": "Unabhängige Kopie von :name",
    "it": "Copia indipendente da :name",
    "es": "Copia independiente de :name"
  },
  "Vous pouvez animer cette séance sans compte. Gardez ce navigateur pour en conserver les commandes.": {
    "en": "You can host this session without an account. Keep using this browser to retain the controls.",
    "de": "Sie können diese Sitzung ohne Konto leiten. Verwenden Sie weiterhin diesen Browser, um die Steuerung zu behalten.",
    "it": "Puoi condurre questa sessione senza un account. Continua a usare questo browser per mantenerne i comandi.",
    "es": "Puedes dirigir esta sesión sin una cuenta. Sigue usando este navegador para conservar los controles."
  },
  "Alerte : limite d’inscription atteinte": {
    "en": "Alert: registration limit reached",
    "de": "Warnung: Registrierungslimit erreicht",
    "it": "Avviso: limite di iscrizione raggiunto",
    "es": "Aviso: límite de inscripción alcanzado"
  },
  ":count demandes depuis un même réseau en 15 minutes": {
    "en": ":count requests from the same network in 15 minutes",
    "de": ":count Anfragen aus demselben Netzwerk in 15 Minuten",
    "it": ":count richieste dalla stessa rete in 15 minuti",
    "es": ":count solicitudes desde la misma red en 15 minutos"
  },
  ":count demandes acceptées en une heure": {
    "en": ":count accepted requests in one hour",
    "de": ":count akzeptierte Anfragen in einer Stunde",
    "it": ":count richieste accettate in un’ora",
    "es": ":count solicitudes aceptadas en una hora"
  },
  ":count demandes acceptées en une journée": {
    "en": ":count accepted requests in one day",
    "de": ":count akzeptierte Anfragen an einem Tag",
    "it": ":count richieste accettate in un giorno",
    "es": ":count solicitudes aceptadas en un día"
  },
  ":count comptes publics en attente de validation": {
    "en": ":count public accounts awaiting verification",
    "de": ":count öffentliche Konten warten auf Bestätigung",
    "it": ":count account pubblici in attesa di verifica",
    "es": ":count cuentas públicas pendientes de verificación"
  },
  "Un plafond d’inscription a été atteint sur :site.\n\nLimite : :limit.\n\nLes nouvelles demandes concernées peuvent être temporairement refusées. Cette alerte n’est envoyée qu’une fois par plafond et par période.\n\nAdministration : :url": {
    "en": "A registration limit has been reached on :site.\n\nLimit: :limit.\n\nAffected new requests may be temporarily refused. This alert is sent only once per limit and period.\n\nAdministration: :url",
    "de": "Ein Registrierungslimit wurde auf :site erreicht.\n\nLimit: :limit.\n\nBetroffene neue Anfragen können vorübergehend abgelehnt werden. Diese Warnung wird nur einmal pro Limit und Zeitraum gesendet.\n\nAdministration: :url",
    "it": "È stato raggiunto un limite di iscrizione su :site.\n\nLimite: :limit.\n\nLe nuove richieste interessate possono essere temporaneamente rifiutate. Questo avviso viene inviato una sola volta per limite e periodo.\n\nAmministrazione: :url",
    "es": "Se ha alcanzado un límite de inscripción en :site.\n\nLímite: :limit.\n\nLas nuevas solicitudes afectadas pueden rechazarse temporalmente. Este aviso se envía una sola vez por límite y período.\n\nAdministración: :url"
  },
  "Contenus externes partagés avec moi": {
    "en": "External content shared with me",
    "de": "Mit mir geteilte externe Inhalte",
    "it": "Contenuti esterni condivisi con me",
    "es": "Contenidos externos compartidos conmigo"
  },
  "Modifier le titre": {
    "en": "Edit title",
    "de": "Titel bearbeiten",
    "it": "Modifica il titolo",
    "es": "Editar el título"
  },
  "Modifier la description": {
    "en": "Edit description",
    "de": "Beschreibung bearbeiten",
    "it": "Modifica la descrizione",
    "es": "Editar la descripción"
  },
  "Ajouter une description": {
    "en": "Add a description",
    "de": "Beschreibung hinzufügen",
    "it": "Aggiungi una descrizione",
    "es": "Añadir una descripción"
  },
  "Enregistrement impossible. Réessayez.": {
    "en": "Unable to save. Please try again.",
    "de": "Speichern nicht möglich. Bitte erneut versuchen.",
    "it": "Impossibile salvare. Riprova.",
    "es": "No se ha podido guardar. Inténtalo de nuevo."
  },
  "Ce contenu a changé ou est en cours de modification. Rechargez la bibliothèque avant de réessayer.": {
    "en": "This content has changed or is being edited. Reload the library before trying again.",
    "de": "Dieser Inhalt wurde geändert oder wird gerade bearbeitet. Laden Sie die Bibliothek neu, bevor Sie es erneut versuchen.",
    "it": "Questo contenuto è cambiato o è in corso di modifica. Ricarica la biblioteca prima di riprovare.",
    "es": "Este contenido ha cambiado o se está editando. Recarga la biblioteca antes de volver a intentarlo."
  },
  "Recharger": {
    "en": "Reload",
    "de": "Neu laden",
    "it": "Ricarica",
    "es": "Recargar"
  },
  "Enregistré": {
    "en": "Saved",
    "de": "Gespeichert",
    "it": "Salvato",
    "es": "Guardado"
  },
  "Admin": {
    "en": "Admin",
    "de": "Admin",
    "it": "Admin",
    "es": "Admin"
  },
  "Progression": {
    "en": "Progress",
    "de": "Fortschritt",
    "it": "Progressi",
    "es": "Progreso"
  },
  "Aucune progression disponible.": {
    "en": "No progress available.",
    "de": "Kein Fortschritt verfügbar.",
    "it": "Nessun progresso disponibile.",
    "es": "No hay progreso disponible."
  },
  "Les informations de l’élève sont incomplètes.": {
    "en": "The student information is incomplete.",
    "de": "Die Angaben zur Schülerin oder zum Schüler sind unvollständig.",
    "it": "Le informazioni dello studente sono incomplete.",
    "es": "La información del alumno está incompleta."
  },
  "Le prénom et le nom doivent contenir entre 2 et 80 caractères.": {
    "en": "First and last names must contain between 2 and 80 characters.",
    "de": "Vor- und Nachname müssen zwischen 2 und 80 Zeichen enthalten.",
    "it": "Nome e cognome devono contenere da 2 a 80 caratteri.",
    "es": "El nombre y los apellidos deben tener entre 2 y 80 caracteres."
  },
  "Indiquez un groupe classe de 1 à 100 caractères.": {
    "en": "Enter a class group of 1 to 100 characters.",
    "de": "Geben Sie eine Klassengruppe mit 1 bis 100 Zeichen ein.",
    "it": "Indica un gruppo classe da 1 a 100 caratteri.",
    "es": "Indica un grupo de clase de 1 a 100 caracteres."
  },
  "Les informations de cet élève ont changé. Rouvrez sa fiche avant de réessayer.": {
    "en": "This student’s information has changed. Reopen their record before trying again.",
    "de": "Die Angaben zu dieser Person haben sich geändert. Öffnen Sie ihr Profil erneut, bevor Sie es noch einmal versuchen.",
    "it": "Le informazioni di questo studente sono cambiate. Riapri la sua scheda prima di riprovare.",
    "es": "La información de este alumno ha cambiado. Vuelve a abrir su ficha antes de intentarlo de nuevo."
  },
  "Les informations de l’élève ont été enregistrées.": {
    "en": "The student information has been saved.",
    "de": "Die Angaben wurden gespeichert.",
    "it": "Le informazioni dello studente sono state salvate.",
    "es": "La información del alumno se ha guardado."
  },
  "Seul l’élève peut modifier son numéro de téléphone.": {
    "en": "Only the student can change their phone number.",
    "de": "Nur die Schülerin oder der Schüler kann die eigene Telefonnummer ändern.",
    "it": "Solo lo studente può modificare il proprio numero di telefono.",
    "es": "Solo el alumno puede modificar su número de teléfono."
  },
  "Enregistrement en cours…": {
    "en": "Saving…",
    "de": "Wird gespeichert…",
    "it": "Salvataggio in corso…",
    "es": "Guardando…"
  },
  "Enregistrement impossible. Rechargez la page pour vérifier le partage.": {
    "en": "Unable to save. Reload the page to check the sharing settings.",
    "de": "Speichern nicht möglich. Laden Sie die Seite neu, um die Freigabe zu überprüfen.",
    "it": "Impossibile salvare. Ricarica la pagina per verificare la condivisione.",
    "es": "No se ha podido guardar. Recarga la página para comprobar la configuración de uso compartido."
  },
  "Cochez un mode pour partager. Décochez les trois modes pour arrêter. Les changements sont enregistrés automatiquement.": {
    "en": "Select a mode to share. Clear all three modes to stop sharing. Changes are saved automatically.",
    "de": "Wählen Sie einen Modus zum Teilen. Entfernen Sie alle drei Häkchen, um die Freigabe zu beenden. Änderungen werden automatisch gespeichert.",
    "it": "Seleziona una modalità per condividere. Deseleziona tutte e tre le modalità per interrompere la condivisione. Le modifiche vengono salvate automaticamente.",
    "es": "Selecciona un modo para compartir. Desmarca los tres modos para dejar de compartir. Los cambios se guardan automáticamente."
  },
  "Partage activé.": {
    "en": "Sharing enabled.",
    "de": "Freigabe aktiviert.",
    "it": "Condivisione attivata.",
    "es": "Uso compartido activado."
  },
  "Partage désactivé.": {
    "en": "Sharing disabled.",
    "de": "Freigabe deaktiviert.",
    "it": "Condivisione disattivata.",
    "es": "Uso compartido desactivado."
  },
  "Arrêter le partage": {
    "en": "Stop sharing",
    "de": "Freigabe beenden",
    "it": "Interrompi la condivisione",
    "es": "Dejar de compartir"
  },
  "Partage arrêté par le propriétaire": {
    "en": "Sharing stopped by the owner",
    "de": "Freigabe vom Eigentümer beendet",
    "it": "Condivisione interrotta dal proprietario",
    "es": "El propietario ha dejado de compartir"
  }
}
JSON, true, 512, JSON_THROW_ON_ERROR);
