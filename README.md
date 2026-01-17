<img src="https://github.com/97703/DockerLab3/blob/main/rysunki/loga_weii.png?raw=true" style="width: 40%; height: 40%" />

> **Programowanie Full-Stack w Chmurze Obliczeniowej**

      dr inż. Sławomir Wojciech Przyłucki

<br>
Termin zajęć:

      środa, godz. 11:30,

Imię i nazwisko:

      Paweł Pieczykolan,
      II rok studiów magisterskich, WOiSI 2.3.

# Wstęp

<p align="justify">Celem zadania było przygotowanie kompletnego środowiska Kubernetes w&nbsp;oparciu o&nbsp;Minikube, umożliwiającego wdrożenie aplikacji webowej w&nbsp;architekturze typu Full-Stack. Wdrożenie miało bazować na jednym z&nbsp;popularnych stacków wymienionych na stronie <a href='https://www.w3schools.com/whatis/whatis_fullstack.asp'>W3Schools</a>, z&nbsp;pełną konfiguracją komponentów w&nbsp;klastrze oraz dostępem do aplikacji pod adresem <code>http://brilliantapp.zad</code>. W&nbsp;ramach realizacji wykorzystano stack LEMP (Linux, Nginx, MySQL, PHP), wdrożony z&nbsp;użyciem Helm w&nbsp;wersji 3.19.4 oraz dwóch chartów: <a href='https://artifacthub.io/packages/helm/wso2/mysql'>wso2/mysql</a> (wersja obrazu 1.6.9 z&nbsp;MySQL 5.7.30) oraz <a href='https://staging.artifacthub.io/packages/helm/k8s-at-home/nginx-php'>k8s-at-home/nginx-php</a> (wersja obrazu 1.2.2 z&nbsp;aplikacją <code>trafex/php-nginx:2.4.0</code>).</p>

<p align="justify">Środowisko zostało uruchomione na maszynie wirtualnej z&nbsp;systemem Ubuntu 24.04.1 LTS. Klaster Minikube został uruchomiony z&nbsp;wykorzystaniem sterownika Docker oraz pluginu sieciowego CNI Calico. Włączono również dodatek Ingress, który pozwolił na wystawienie aplikacji na zewnątrz klastra.</p>

<p align="justify">Wdrożenie aplikacji odbyło się poprzez instalację chartów Helm oraz przygotowanie dodatkowych plików konfiguracyjnych dla wybranych Charów. Do wgrywania plików aplikacji wykorzystano obiekt <code>ConfigMap</code> o&nbsp;nazwie <code>lemp-app</code>, który ładował zawartość katalogu <code>./lemp-app</code>. Aplikacja realizowała prostą funkcjonalność listy obecności z&nbsp;pełnym zakresem operacji CRUD na tabeli w&nbsp;bazie danych MySQL. Konfiguracja chartu <code>nginx-php</code> została zmodyfikowana tak, aby korzystała z&nbsp;Ingressa oraz z&nbsp;danych z&nbsp;<code>ConfigMap</code>.</p>

<p align="justify">Dostępność aplikacji została zweryfikowana poprzez utworzenie tunelu Minikube oraz testy z&nbsp;użyciem polecenia <code>curl</code> i&nbsp;wpisu do pliku <code>/etc/hosts</code>. W&nbsp;ramach części nieobowiązkowej wdrożono mechanizm aktualizacji aplikacji z&nbsp;zachowaniem ciągłości działania – poprzez aktualizację <code>ConfigMap</code> oraz zastosowanie strategii <code>RollingUpdate</code>. Dodano również zmienną środowiskową <code>TZ=UTC</code>, której obecność została potwierdzona w&nbsp;działającym kontenerze po aktualizacji.</p>

<p align="justify">W celu zapewnienia wysokiej dostępności i&nbsp;odporności aplikacji na błędy, skonfigurowano sondy <code>liveness</code> i&nbsp;<code>readiness</code> dla obu komponentów: <code>nginx-php</code> oraz <code>my-mysql</code>. Ich działanie zostało potwierdzone poprzez wymuszenie restartu kontenerów oraz analizę zdarzeń w&nbsp;Kubernetes. Dodatkowo wprowadzono konfigurację strategii aktualizacji dla bazy danych oraz ustawiono strefę czasową <code>UTC</code>. Wszystkie kroki zostały udokumentowane i&nbsp;zilustrowane w&nbsp;dalszej części sprawozdania.</p>

# 1. Uruchomienie klastra

<p align="justify">W ramach przygotowania środowiska do wdrożenia aplikacji Full-Stack uruchomiono nowy klaster Kubernetes z&nbsp;wykorzystaniem Minikube. Konfiguracja została przeprowadzona na maszynie wirtualnej z&nbsp;systemem Ubuntu 24.04.1 LTS, działającej w&nbsp;środowisku Oracle VirtualBox. Do uruchomienia klastra wykorzystano sterownik <code>Docker</code> oraz plugin sieciowy <code>CNI Calico</code>. Polecenie użyte do startu klastra:</p>

    minikube start --driver=docker --network-plugin=cni --cni=calico

<p align="justify">Proces inicjalizacji klastra objął pobranie obrazu bazowego, utworzenie kontenera z&nbsp;2 CPU i&nbsp;3900 MB RAM oraz przygotowanie komponentów Kubernetes w&nbsp;wersji <code>v1.34.0</code>. Minikube automatycznie skonfigurował kontekst <code>kubectl</code> do pracy z&nbsp;nowym klastrem, umożliwiając zarządzanie zasobami w&nbsp;przestrzeni nazw <code>default</code>.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek1.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 1. Uruchomienie klastra Minikube z&nbsp;CNI Calico</i>
</p>

<p align="justify">Kolejnym krokiem było włączenie dodatku <code>Ingress</code>, który umożliwia wystawienie aplikacji na zewnątrz klastra. W&nbsp;terminalu wykonano polecenie:</p>

    minikube addons enable ingress
  
<p align="justify">Minikube pobrał obrazy kontrolera Ingress oraz komponentów certgen, a&nbsp;następnie utworzył zasoby w&nbsp;przestrzeni nazw <code>ingress-nginx</code>. Status działania kontrolera został zweryfikowany za pomocą polecenia:</p>

    kubectl get pods -n ingress-nginx
  
<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek2.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 2. Aktywacja dodatku Ingress i&nbsp;weryfikacja działania kontrolera</i>
</p>

<p align="justify">Wynik polecenia potwierdził poprawne utworzenie zasobów, w&nbsp;tym kontrolera <code>ingress-nginx-controller</code>, który rozpoczął proces inicjalizacji. Tym samym środowisko było gotowe do  wdrażania aplikacji.</p>

# 2. Konfiguracja i&nbsp;uruchomienie Chartu nginx-php
<p align="justify">Pierwszym krokiem po uruchomieniu klastra było przygotowanie konfiguracji dla chartu <code>k8s-at-home/nginx-php</code>. Zamiast korzystać z&nbsp;domyślnych wartości, utworzono plik <code>value-nginx.yaml</code>, w&nbsp;którym zdefiniowano sposób działania serwisu, typ kontrolera oraz podstawową konfigurację serwera Nginx. Dzięki temu możliwe było precyzyjne dostosowanie wdrożenia do wymagań zadania.</p>

**value-nginx.yaml**
```yaml
service:
  main:
    enabled: true # włącza główny serwis
    type: ClusterIP # ustawia jego typ 
    ports:
      http:
        enabled: true # aktywuje port HTTP

controller:
  type: deployment
  mainContainer:
    ports:
      http:
        enabled: true # włączono port HTTP w kontenerze
```

<p align="justify">Po przygotowaniu pliku konfiguracyjnego wykonano instalację chartu za pomocą polecenia:</p>

    helm install nginx-php k8s-at-home/nginx-php -f value-nginx.yaml
    
<p align="justify">Polecenie to utworzyło obiekt <code>Pod</code> o&nbsp;nazwie <code>nginx-php</code> w&nbsp;przestrzeni nazw <code>default</code>. W&nbsp;wyniku instalacji powstał obiekt <code>Deployment</code> oraz odpowiadający mu <code>Service</code>, co zostało potwierdzone komunikatem o&nbsp;statusie <code>deployed</code>. Od tego momentu frontend aplikacji był dostępny w&nbsp;klastrze jako serwis typu <code>ClusterIP</code> i&nbsp;mógł zostać powiązany z&nbsp;Ingressem.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek3.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 3. Instalacja Charta nginx-php</i>
</p>

# 3. Konfiguracja i&nbsp;uruchomienie Chartu MySQL

<p align="justify">W warstwie backendowej, bazodanowej aplikacji wykorzystano bazę danych MySQL wdrożoną za pomocą chartu <code>wso2/mysql</code> w&nbsp;wersji <code>1.6.9</code>, który zawiera obraz <code>mysql:5.7.30</code>. Przed instalacją przygotowano plik <code>value-mysql.yaml</code>, zawierający szczegółową konfigurację instancji bazy danych, w&nbsp;tym dane dostępowe, sondy zdrowia oraz typ serwisu. Dzięki temu możliwe było precyzyjne dostosowanie zachowania kontenera do wymagań aplikacji oraz zapewnienie odporności na błędy.</p>

**value-mysql.yaml**
```yaml
# definicja danych dostępowych do bazy danych
mysqlRootPassword: "rootpass"
mysqlUser: "app"
mysqlPassword: "apppass"
mysqlDatabase: "appdb"

# konfiguracja sondy, która sprawdza, czy kontener nie zawiesił się
livenessProbe:
  initialDelaySeconds: 60
  periodSeconds: 15
  timeoutSeconds: 5
  failureThreshold: 6

# konfiguracja sondy, która sprawdzy, czy kontener jest gotowy do obsługi zapytań
# parametry sond livenessProbe i readinessProbe zostały dobrane tak, aby dać kontenerowi czas na inicjalizację, a jednocześnie szybko wykrywać awarie.
readinessProbe:
  initialDelaySeconds: 30
  periodSeconds: 15
  timeoutSeconds: 5
  failureThreshold: 6

 # serwis nie zostaje wystawiony na zewnątrz
service:
  type: ClusterIP
```

<p align="justify">Instalacja chartu została wykonana poleceniem:</p>

    helm install my-mysql wso2/mysql --version 1.6.9 -f value-mysql.yaml

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek4.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 4. Instalacja Charta mysql</i>
</p>
  
<p align="justify">W wyniku instalacji utworzony został obiekt <code>Pod</code>, <code>Deployment</code> oraz odpowiadający mu <code>Service</code>. Po zakończeniu wdrożenia baza danych była gotowa do obsługi zapytań z&nbsp;aplikacji webowej, a&nbsp;jej stabilność była monitorowana przez sondy zdrowia.</p>

# 4. Utworzenie aplikacji

<p align="justify">W ramach warstwy frontendowej przygotowano prostą aplikację webową w&nbsp;języku PHP, której celem jest zarządzanie listą obecności studentów. Aplikacja umożliwia dodawanie, edytowanie, usuwanie oraz przeglądanie wpisów w&nbsp;tabeli <code>attendance</code> znajdującej się w&nbsp;bazie danych MySQL. Całość została umieszczona w&nbsp;katalogu <code>lemp-app</code>, a&nbsp;następnie wgrana do klastra Kubernetes jako <code>ConfigMap</code>, co pozwala na jej automatyczne podłączenie do kontenera Nginx-PHP.</p>

<p align="justify">Aplikacja składa się z&nbsp;pięciu plików PHP:</p>

**db.php**
```php
<?php
$host = "my-mysql"; // nazwa serwisu w Kubernetes
$user = "app";
$password = "apppass";
$database = "appdb";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
```

<p align="justify">Plik <code>db.php</code> odpowiada za połączenie z&nbsp;bazą danych MySQL. Wykorzystuje nazwę serwisu <code>my-mysql</code> zdefiniowaną w&nbsp;Kubernetes oraz dane dostępowe skonfigurowane w&nbsp;chartcie MySQL. W&nbsp;przypadku błędu połączenia aplikacja wyświetla komunikat diagnostyczny.</p>

**index.php**
```php
<?php
include 'db.php';

$result = $conn->query("SELECT * FROM attendance ORDER BY id DESC");

echo "<h1>Lista obecności</h1>";
echo "<a href='add.php'>Dodaj studenta</a><br><br>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Imię i nazwisko</th><th>Index</th><th>Obecność</th><th>Akcje</th></tr>";

while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>".$row['id']."</td>";
    echo "<td>".$row['student_name']."</td>";
    echo "<td>".$row['student_index']."</td>";
    echo "<td>".($row['present'] ? "✓" : "✗")."</td>";
    echo "<td>
            <a href='edit.php?id=".$row['id']."'>Edytuj</a> | 
            <a href='delete.php?id=".$row['id']."'>Usuń</a>
          </td>";
    echo "</tr>";
}
echo "</table>";
?>
```

<p align="justify">Plik <code>index.php</code> pełni rolę strony głównej aplikacji. Pobiera dane z&nbsp;tabeli <code>attendance</code> i&nbsp;wyświetla je w&nbsp;formie tabeli HTML. Umożliwia przejście do formularza dodawania nowego studenta oraz edycji lub usunięcia istniejących wpisów.</p>

**add.php**
```php
<?php
include 'db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['student_name'];
    $index = $_POST['student_index'];
    $present = isset($_POST['present']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO attendance (student_name, student_index, present) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $index, $present);
    $stmt->execute();
    header("Location: index.php");
    exit;
}
?>

<h1>Dodaj studenta</h1>
<form method="post">
    Imię i nazwisko: <input type="text" name="student_name" required><br>
    Index: <input type="text" name="student_index" required><br>
    Obecność: <input type="checkbox" name="present"><br>
    <input type="submit" value="Dodaj">
</form>
<a href="index.php">Powrót do listy</a>
```

<p align="justify">Plik <code>add.php</code> zawiera formularz umożliwiający dodanie nowego wpisu do listy obecności. Po przesłaniu danych formularza następuje ich walidacja i&nbsp;zapis do bazy danych za pomocą przygotowanego zapytania SQL.</p>

**edit.php**
```php
<?php
include 'db.php';

$id = $_GET['id'];
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['student_name'];
    $index = $_POST['student_index'];
    $present = isset($_POST['present']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE attendance SET student_name=?, student_index=?, present=? WHERE id=?");
    $stmt->bind_param("ssii", $name, $index, $present, $id);
    $stmt->execute();
    header("Location: index.php");
    exit;
}

$result = $conn->query("SELECT * FROM attendance WHERE id=$id");
$row = $result->fetch_assoc();
?>

<h1>Edytuj studenta</h1>
<form method="post">
    Imię i nazwisko: <input type="text" name="student_name" value="<?= $row['student_name'] ?>" required><br>
    Index: <input type="text" name="student_index" value="<?= $row['student_index'] ?>" required><br>
    Obecność: <input type="checkbox" name="present" <?= $row['present'] ? 'checked' : '' ?>><br>
    <input type="submit" value="Zapisz">
</form>
<a href="index.php">Powrót do listy</a>
```

<p align="justify">Plik <code>edit.php</code> umożliwia edycję istniejącego wpisu. Na podstawie parametru <code>id</code> pobierane są dane studenta, które następnie można zmodyfikować i&nbsp;zapisać do bazy danych.</p>

**delete.php**
```php
<?php
include 'db.php';

$id = $_GET['id'];
$conn->query("DELETE FROM attendance WHERE id=$id");
header("Location: index.php");
exit;
?>
```

<p align="justify">Plik <code>delete.php</code> realizuje funkcję usuwania wpisu z&nbsp;bazy danych. Po wykonaniu zapytania <code>DELETE</code> następuje przekierowanie na stronę główną.</p>

<p align="justify">Cała aplikacja została wgrana do klastra Kubernetes jako <code>ConfigMap</code> o&nbsp;nazwie <code>lemp-app</code>. W&nbsp;terminalu wykonano polecenie:</p>

    kubectl create configmap lemp-app --from-file=./lemp-app

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek5.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 5. Utworzenie plików aplikacji i&nbsp;wgranie Configmapa do klastra Kubernetesa</i>
</p>

<p align="justify">Dzięki temu po podpięciu obiektu <code>ConfigMap</code> pod kontener nginx-php pliki aplikacji zostaną automatycznie zamontowane w&nbsp;kontenerze frontendowym, a&nbsp;aplikacja będzie gotowa do działania i&nbsp;komunikacji z&nbsp;bazą danych MySQL.</p>

# 5. Implementacja aplikacji

<p align="justify">Po utworzeniu plików aplikacji w&nbsp;katalogu <code>lemp-app</code> oraz ich wgraniu do klastra jako <code>ConfigMap</code>, wykonano implementację funkcjonalną aplikacji.</p>

<p align="justify">W pierwszym kroku utworzono tabelę <code>attendance</code> bezpośrednio w&nbsp;bazie danych <code>appdb</code>, korzystając z&nbsp;sesji MySQL uruchomionej wewnątrz kontenera:</p>

```sql
USE appdb;

CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  student_index VARCHAR(20) NOT NULL,
  present BOOLEAN NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

<p align="justify">Struktura tabeli została dobrana tak, aby umożliwić przechowywanie podstawowych informacji o&nbsp;studentach: imienia i&nbsp;nazwiska, numeru indeksu, statusu obecności oraz znacznika czasowego utworzenia wpisu. Kolumna <code>present</code> została zdefiniowana jako <code>BOOLEAN</code> z&nbsp;domyślną wartością <code>0</code>, co pozwala na prostą interpretację obecności w&nbsp;interfejsie użytkownika.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek6.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 6. Utworzenie tabeli "attendance" w&nbsp;kontenerze my-mysql</i>
</p>

<p align="justify">Aplikacja została zamontowana w&nbsp;kontenerze <code>nginx-php</code> poprzez konfigurację <code>persistence</code> typu <code>configMap</code>, wskazującą na <code>lemp-app</code> jako źródło plików. Pliki zostały umieszczone w&nbsp;katalogu <code>/var/www/html</code>, co pozwoliło Nginxowi na ich bezpośrednie serwowanie.</p>

<p align="justify">W celu wystawienia aplikacji na zewnątrz klastra skonfigurowano obiekt <code>Ingress</code> w&nbsp;pliku <code>value-nginx-map.yaml</code> będącym kopią pliku <code>value-nginx.yaml</code>. W&nbsp;sekcji <code>ingress.main</code> zdefiniowano klasę <code>nginx</code>, nazwę hosta <code>brilliantapp.zad</code> oraz ścieżkę <code>/</code> kierującą ruch do serwisu <code>nginx-php</code> na porcie <code>8080</code>. Zmiany w&nbsp;konfiguracji:</p>

**value-nginx-map.yaml**
```yaml
persistence:
  main:
    enabled: true
    type: configMap
    name: lemp-app
    mountPath: /var/www/html

ingress:
  main:
    enabled: true
    ingressClassName: nginx
    hosts:
      - host: brilliantapp.zad
        paths:
          - path: /
            pathType: Prefix
            service:
              name: nginx-php
              port: 8080
```

<p align="justify">Po dodaniu konfiguracji Ingressu i&nbsp;ConfigMap wykonano aktualizację wdrożenia za pomocą polecenia:</p>

    helm upgrade nginx-php k8s-at-home/nginx-php -f value-nginx-map.yaml

<p align="justify">Dzięki temu aplikacja została wystawiona pod adresem <code>http://brilliantapp.zad</code></p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek7.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 7. Aktualizacja kontenera nginx-php</i>
</p>

# 6. Test dostępności aplikacji

<p align="justify">Po wdrożeniu aplikacji oraz konfiguracji Ingressu wykonano testy dostępności, mające na celu potwierdzenie poprawnego działania wszystkich komponentów w&nbsp;klastrze. Pierwszym krokiem było uruchomienie tunelu Minikube za pomocą polecenia:</p>

    minikube tunnel

<p align="justify">Tunel umożliwił przekierowanie ruchu z&nbsp;zewnątrz do serwisów typu <code>ClusterIP</code> poprzez kontroler Ingress. Status tunelu został potwierdzony jako aktywny, bez błędów po stronie routera ani samego Minikube.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek8.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 8. Utworzenie tunelu</i>
</p>

<p align="justify">Następnie wykonano polecenie:</p>

    kubectl get all

<p align="justify">które potwierdziło, że wszystkie obiekty – <code>Pods</code>, <code>Services</code>, <code>Deployments</code> – są w&nbsp;stanie <code>Running</code></p>

<p align="justify">Dostępność aplikacji została przetestowana za pomocą polecenia <code>curl</code> z&nbsp;nagłówkiem <code>Host</code>, wskazującym na skonfigurowaną nazwę domenową:</p>

    curl -H "Host: brilliantapp.zad" http://192.168.49.2

<p align="justify">Polecenie zwróciło poprawną odpowiedź HTTP oraz zawartość strony, potwierdzając, że Ingress poprawnie przekierowuje ruch do serwisu <code>nginx-php</code>.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek9.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 9. Wynik wykonania polecenia Curl</i>
</p>

<p align="justify">W celu umożliwienia testów w&nbsp;przeglądarce, dodano wpis do pliku <code>/etc/hosts</code>:</p>

    192.168.49.2 brilliantapp.zad

<p align="justify">Dzięki temu możliwe było otwarcie aplikacji w&nbsp;przeglądarce pod adresem <code>http://brilliantapp.zad</code>. 

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek10.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 10. Widok aplikacji – lista obecności</i>
</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek11.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 11. Widok aplikacji – dodanie rekordu do listy obecności</i>
</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek12.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 12. Widok aplikacji – lista obecności po dodaniu użytkownika</i>
</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek13.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 13. Widok aplikacji – edycja rekordu listy obecności</i>
</p>

<p align="justify">Aplikacja działa stabilnie, a&nbsp;komunikacja z&nbsp;bazą danych MySQL przebiega bez zakłóceń. Tym samym potwierdzono, że wdrożenie aplikacji w&nbsp;klastrze Minikube zakończyło się sukcesem.</p>

# 7. Aktualizacja aplikacji nginx-php

<p align="justify">W ramach części nieobowiązkowej zadania przeprowadzono aktualizację aplikacji webowej bez przerywania jej działania. Celem było potwierdzenie, że użytkownik końcowy nie doświadcza żadnych przerw w&nbsp;dostępności aplikacji podczas procesu aktualizacji.</p>

<p align="justify">W pliku <code>value-nginx-map.yaml</code> wprowadzono następujące zmiany:</p>

**value-nginx-map.yaml**
```yaml
controller:
  type: deployment
  strategy: RollingUpdate
  rollingUpdate:
    maxUnavailable: 0
    maxSurge: 1

env:
  TZ: "UTC"
```

<p align="justify">Aktualizacja została wykonana poleceniem:</p>

    helm upgrade nginx-php k8s-at-home/nginx-php -f value-nginx-map.yaml

<p align="justify">
Po wykonaniu aktualizacji rozpoczął się proces wymiany replik w&nbsp;ramach Deploymentu <code>nginx-php</code>.  
W&nbsp;kolejnych wywołaniach polecenia <code>kubectl get all</code> można było zaobserwować:
</p>

- pojawienie się nowej repliki `nginx-php-5b9b7c54d4-*` w&nbsp;stanie `Running`,
- równoległe działanie starej repliki `nginx-php-76478f9889-*`,
- stopniowe przechodzenie starej repliki w&nbsp;stan `Terminating`,
- finalnie – pełne przejęcie ruchu przez nową replikę i&nbsp;usunięcie starej repliki.

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek14.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 14. Przebieg aktualizacji – równoległe działanie replik</i>
</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek15.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 15. Przebieg aktualizacji – stara replika w&nbsp;stanie Terminating</i>
</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek16.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 16. Przebieg aktualizacji – usunięcie starej repliki</i>
</p>

<p align="justify">W celu potwierdzenia, że aplikacja nie przestaje odpowiadać podczas aktualizacji, uruchomiono test ciągłości za pomocą polecenia:</p>

    while true; do curl -s -o /dev/null -w "%{http_code} - $(date '+%H:%M:%S')\n" -H "Host: brilliantapp.zad" http://192.168.49.2:80; sleep 10; done
    
<p align="justify">Polecenie wysyłało zapytania HTTP co 10 sekund, rejestrując kod odpowiedzi oraz znacznik czasu. Wyniki testu wykazały, że przez cały czas trwania aktualizacji aplikacja zwracała kod <code>200</code>, co oznacza pełną dostępność.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek17.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 17. Test dostępności aplikacji podczas aktualizacji</i>
</p>

<p align="justify">Dodatkowo, po zakończeniu aktualizacji, wykonano polecenie:</p>

    kubectl exec pod/nginx-php-76478f9889-95n75 -- printenv TZ
    
<p align="justify">które zwróciło wartość <code>UTC</code>, potwierdzając, że nowa wersja aplikacji została poprawnie wdrożona.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek18.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 18. Weryfikacja zmiennej środowiskowej TZ po aktualizacji</i>
</p>

<p align="justify">Wszystkie testy potwierdziły, że aktualizacja została przeprowadzona zgodnie z&nbsp;założeniami – bez przestojów, z&nbsp;zachowaniem dostępności i&nbsp;poprawnym wdrożeniem zmian konfiguracyjnych.</p>

> [!WARNING]
> <p align="justify">W przypadku używanego w&nbsp;projekcie chartu MySQL zastosowano strategię <code>Recreate</code>, ponieważ chart ten celowo nie korzysta ze <code>StatefulSet</code> i&nbsp;został zaprojektowany jako prosta, jednowęzłowa instancja bazy danych. MySQL w&nbsp;takim układzie działa jako pojedynczy pod z&nbsp;podłączonym PVC. Z&nbsp;tego powodu strategia <code>RollingUpdate</code> byłaby niebezpieczna — mogłaby doprowadzić do jednoczesnego uruchomienia dwóch podów korzystających z&nbsp;tego samego wolumenu, co grozi uszkodzeniem danych. <code>Recreate</code> gwarantuje, że stary pod zostanie całkowicie zatrzymany przed uruchomieniem nowego, choć oznacza to krótką przerwę w&nbsp;dostępności bazy danych podczas aktualizacji.</p>
>
> <p align="justify">Aby uniknąć jakichkolwiek przerw w&nbsp;działaniu MySQL, administrator musiałby wdrożyć pełną architekturę wysokiej dostępności — co najmniej replikację master, mechanizm automatycznego failovera, a&nbsp;następnie użyć <code>StatefulSet</code> z&nbsp;odpowiednią polityką aktualizacji. Dopiero wtedy możliwe byłoby bezprzestojowe przełączanie ruchu między instancjami i&nbsp;wykonywanie aktualizacji bez zatrzymywania bazy danych. Zgodnie z&nbsp;dokumentacją, dla tego charta nie jest możliwe ustawienie <code>StatefulSet</code>.</p>

# 8. Modyfikacja aplikacji internetowej i&nbsp;aktualizacja ConfigMap

<p align="justify">W ramach dalszego rozwoju aplikacji webowej dokonano modyfikacji pliku <code>index.php</code>, dodając integrację z&nbsp;biblioteką <strong>Bootstrap 5</strong>. Celem było poprawienie estetyki interfejsu użytkownika oraz zwiększenie czytelności tabeli obecności. Zmieniono strukturę HTML, dodano klasy CSS oraz komponenty Bootstrap.</p>

**index.php**
```php
<?php
include 'db.php';

$result = $conn->query("SELECT * FROM attendance ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Lista obecności</title>

    <!-- Bootstrap 5 -->
    <link 
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" 
        rel="stylesheet"
    >
</head>
<body class="bg-light">

<div class="container mt-5">

    <h1 class="mb-4">Lista obecności</h1>

    <a href="add.php" class="btn btn-primary mb-3">Dodaj studenta</a>

    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Imię i nazwisko</th>
                <th>Index</th>
                <th>Obecność</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['student_name'] ?></td>
                <td><?= $row['student_index'] ?></td>
                <td class="text-center">
                    <?= $row['present'] ? "✓" : "✗" ?>
                </td>
                <td>
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edytuj</a>
                    <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger">Usuń</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</div>

</body>
</html>
```

<p align="justify">Po zapisaniu zmian w&nbsp;pliku, zaktualizowano obiekt <code>ConfigMap</code> w&nbsp;Kubernetes, korzystając z&nbsp;polecenia:</p>

    kubectl create configmap lemp-app --from-file=index.php=./lemp-app/index.php --dry-run=client -o yaml | kubectl apply -f

<p align="justify">Dzięki temu nowa wersja pliku została natychmiast wstrzyknięta do kontenera <code>nginx-php</code>, bez potrzeby restartu aplikacji ani ponownego wdrażania chartu.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek19.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 19. Zaktualizowany interfejs aplikacji z&nbsp;użyciem Bootstrap</i>
</p>

<p align="justify">Dzięki wykorzystaniu <code>ConfigMap</code> jako źródła plików aplikacji, możliwe było szybkie i&nbsp;bezpieczne wprowadzanie zmian w&nbsp;kodzie bez przerywania działania kontenera.</p>

# 9. Test działania sond healthcheck dla nginx-php

<p align="justify">W chartcie <code>nginx-php</code> zastosowano predefiniowane sondy <strong>healthcheck</strong> – <code>liveness</code>, <code>readiness</code> oraz <code>startup</code> – które są automatycznie generowane na podstawie obrazu <code>trafex/php-nginx</code>. Chart nie udostępnia możliwości modyfikacji parametrów tych sond (takich jak <code>initialDelaySeconds</code>, <code>timeoutSeconds</code>, <code>failureThreshold</code>) z&nbsp;poziomu pliku <code>YAML</code>, co ogranicza kontrolę nad ich zachowaniem i&nbsp;czasem reakcji.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek20.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 20. Predefiniowane sondy w&nbsp;kontenerze nginx-php</i>
</p>

<p align="justify">W celu przetestowania działania sond przeprowadzono kontrolowany eksperyment polegający na ręcznym zakończeniu procesu <code>nginx</code> wewnątrz kontenera <code>nginx-php</code> za pomocą polecenia:</p>

    kubectl exec nginx-php-76478f9889-95n75 -- pkill nginx

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek21.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 21. Zabicie procesu nginx w&nbsp;kontenerze nginx-php</i>
</p>

<p align="justify">Po zakończeniu procesu sondy wykryły brak odpowiedzi na porcie TCP, co skutkowało restartem kontenera przez <code>kubelet</code>. W&nbsp;logach zdarzeń pojawiły się komunikaty.

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek22.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 22. Ostrzeżenia od sond healthcheck</i>
</p>

<p align="justify">Sondy <code>readiness</code> zgłaszały błędy, co potwierdza ich aktywne działanie i&nbsp;skuteczność w&nbsp;monitorowaniu stanu aplikacji. Dzięki temu Kubernetes automatycznie wyłączył niedziałający kontener i&nbsp;uruchomił nowy, przywracając pełną dostępność aplikacji.</p>

<p align="justify">Mimo braku możliwości dostosowania parametrów sond, ich domyślna konfiguracja okazała się wystarczająca w&nbsp;kontekście prostego wdrożenia testowego.</p>
  
# 10. Test działania sond healthcheck dla MySQL

<p align="justify">W celu zwiększenia odporności bazy danych MySQL na błędy oraz zapewnienia kontrolowanego procesu aktualizacji, zmodyfikowano konfigurację chartu <code>wso2/mysql</code>. Zmiany objęły zarówno sondy <code>liveness</code> i&nbsp;<code>readiness</code>. Celem było lepsze monitorowanie stanu kontenera.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek23.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 23. Predefiniowane sondy kontenera my-mysql</i>
</p>

<p align="justify">W pliku <code>value-mysql.yaml</code> wprowadzono następujące parametry:</p>

**value-mysql.yaml**
```yaml
# sprawdza, czy kontener działa poprawnie; w przypadku braku odpowiedzi przez 6 cykli (czyli 60 sekund), kontener jest restartowany
livenessProbe:
  initialDelaySeconds: 60
  periodSeconds: 10 # skrócono interwał czasowy między kolejnymi próbami sprawdzenia zdrowia kontenera
  timeoutSeconds: 5
  failureThreshold: 6

# decyduje, czy kontener jest gotowy do obsługi zapytań; jeśli nie, ruch do niego jest wstrzymywany
readinessProbe:
  initialDelaySeconds: 30
  periodSeconds: 10 # skrócono interwał czasowy między kolejnymi próbami sprawdzenia zdrowia kontenera
  timeoutSeconds: 5
  failureThreshold: 6

# zapewnia, że stary pod zostanie całkowicie usunięty przed uruchomieniem nowego, co chroni przed kolizją na poziomie wolumenów PVC
strategy:
  type: Recreate

# ujednolica strefę czasową w kontenerze
timezone: "UTC"
```
<p align="justify">Zastosowano konfigurację dla kontenera my-mysql poleceniem:</p>

    helm upgrade my-mysql wso2/mysql --version 1.6.9 -f value-mysql.yaml
    
<p align="justify">Po wykonaniu <code>helm upgrade</code> dla chartu MySQL, rozpoczął się proces wymiany podów zgodnie ze strategią <code>Recreate</code>.</p>

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek24.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 24. Kolejne zmiany stanu i&nbsp;liczby Podów MySQL po wgraniu aktualizacji konfiguracji</i>
</p>

<p align="justify">Sondy <code>liveness</code> i&nbsp;<code>readiness</code> zostały aktywowane zgodnie z&nbsp;konfiguracją. W&nbsp;przypadku błędów (np. nieudane połączenie z&nbsp;<code>mysqladmin</code>), w&nbsp;logach zdarzeń pojawiały się komunikaty. Do wyświetlenia użyto polecenia:</p>

    kubectl get events --sort-by=.metadata.creationTimestamp

<p align="center">
<img src="https://raw.githubusercontent.com/97703/FullStack_Zad2/main/rysunki/rysunek25.png" style="width: 70%; height: 70%" />
</p>
<p align="center">
<i>Rys. 25. Logi zdarzeń dla kontenera my-mysql</i>
</p>

<p align="justify">Komunikaty te potwierdzają, że sondy skutecznie wykrywają problemy z&nbsp;kontenerem i&nbsp;uruchamiają mechanizmy samonaprawy. Dodatkowo, dzięki strategii <code>Recreate</code> uniknięto ryzyka jednoczesnego dostępu do wolumenu przez dwa pody, co mogłoby prowadzić do uszkodzenia danych.</p>

# Podsumowanie

<p align="justify">W ramach niniejszego zadania przeprowadzono pełne wdrożenie aplikacji webowej w&nbsp;architekturze LEMP (Linux + Nginx + MySQL + PHP) z&nbsp;wykorzystaniem klastra Kubernetes uruchomionego lokalnie w&nbsp;środowisku Minikube. Proces obejmował zarówno konfigurację infrastruktury, jak i&nbsp;implementację aplikacji oraz testy dostępności i&nbsp;odporności na błędy.</p>

<p align="justify">Na początku uruchomiono klaster Minikube z&nbsp;pluginem sieciowym Calico oraz aktywowano dodatek Ingress, umożliwiający wystawienie aplikacji na zewnątrz. Następnie wdrożono dwa główne komponenty: <code>nginx-php</code> jako frontend oraz <code>my-mysql</code> jako backend bazodanowy. Oba komponenty zostały zainstalowane za pomocą Helm chartów, z&nbsp;precyzyjnie przygotowanymi plikami konfiguracyjnymi <code>values.yaml</code>.</p>

<p align="justify">Aplikacja webowa została zaimplementowana w&nbsp;PHP i&nbsp;umożliwia zarządzanie listą obecności studentów. Składa się z&nbsp;pięciu plików: <code>index.php</code>, <code>db.php</code>, <code>add.php</code>, <code>edit.php</code> oraz <code>delete.php</code>. Pliki zostały wgrane do klastra jako <code>ConfigMap</code> i&nbsp;zamontowane w&nbsp;kontenerze frontendowym. Po utworzeniu tabeli <code>attendance</code> w&nbsp;bazie danych, aplikacja została przetestowana pod kątem dodawania, edytowania i&nbsp;usuwania rekordów.</p>

<p align="justify">W kolejnych etapach skonfigurowano obiekt Ingress oraz uruchomiono tunel Minikube, co umożliwiło dostęp do aplikacji pod adresem <code>http://brilliantapp.zad</code>. Przeprowadzono testy dostępności z&nbsp;użyciem <code>curl</code> oraz wpisu w&nbsp;<code>/etc/hosts</code>, które potwierdziły poprawne działanie aplikacji.</p>

<p align="justify">W ramach aktualizacji aplikacji zastosowano strategię <code>RollingUpdate</code> dla komponentu <code>nginx-php</code>, co pozwoliło na bezprzestojowe wdrożenie nowej wersji z&nbsp;dodatkiem Bootstrap. Testy ciągłości działania wykazały, że aplikacja nie przestaje odpowiadać podczas aktualizacji, a&nbsp;mechanizmy Kubernetes skutecznie zarządzają replikami.</p>

<p align="justify">Dla komponentu MySQL zastosowano strategię <code>Recreate</code>, zgodną z&nbsp;charakterystyką baz danych jako komponentów stanowych. Dodatkowo skonfigurowano sondy <code>liveness</code> i&nbsp;<code>readiness</code>, które skutecznie wykrywały błędy i&nbsp;uruchamiały mechanizmy samonaprawy. Testy wykazały, że kontener MySQL jest poprawnie monitorowany i&nbsp;restartowany w&nbsp;przypadku awarii.</p>

<p align="justify">Całość wdrożenia została przeprowadzona zgodnie z&nbsp;dobrymi praktykami Kubernetes, z&nbsp;uwzględnieniem strategii aktualizacji, sond zdrowia, separacji komponentów oraz automatyzacji konfiguracji. Projekt potwierdził, że nawet w&nbsp;lokalnym środowisku Minikube możliwe jest zbudowanie w&nbsp;pełni funkcjonalnej aplikacji Full-Stack opartej na kontenerach, z&nbsp;zachowaniem wysokiej dostępności i&nbsp;odporności na błędy.</p>
