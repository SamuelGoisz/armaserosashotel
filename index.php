<?php
require_once 'config.php';

$hoteis = $pdo->query("SELECT * FROM hotel ORDER BY id_hotel")->fetchAll();

$quartos = $pdo->query("
    SELECT q.*, t.nome AS tipo_quarto, t.preco_diaria
    FROM quarto q
    JOIN tipo_quarto t ON q.id_tipo_quarto = t.id_tipo_quarto
    ORDER BY q.numero
")->fetchAll();

$hospedes = $pdo->query("
    SELECT * FROM hospede ORDER BY nome
")->fetchAll();

$reservas = $pdo->query("
    SELECT r.*, h.nome AS hospede
    FROM reserva r
    JOIN hospede h ON r.id_hospede = h.id_hospede
    ORDER BY r.data_reserva DESC
")->fetchAll();

$totalHoteis = $pdo->query("SELECT COUNT(*) FROM hotel")->fetchColumn();
$totalQuartos = $pdo->query("SELECT COUNT(*) FROM quarto")->fetchColumn();
$totalHospedes = $pdo->query("SELECT COUNT(*) FROM hospede")->fetchColumn();
$totalReservas = $pdo->query("SELECT COUNT(*) FROM reserva")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $idHospede = $_POST['id_hospede'];
    $idQuarto = $_POST['id_quarto'];
    $checkin = $_POST['checkin'];
    $checkout = $_POST['checkout'];
    $quantidade = $_POST['quantidade_hospedes'];

    $dias = (strtotime($checkout) - strtotime($checkin)) / 86400;

    if ($dias <= 0) {
        die("Data de checkout deve ser posterior ao check-in.");
    }

    $stmt = $pdo->prepare("
        SELECT q.id_quarto, t.preco_diaria, t.capacidade
        FROM quarto q
        JOIN tipo_quarto t ON q.id_tipo_quarto = t.id_tipo_quarto
        WHERE q.id_quarto = ?
    ");
    $stmt->execute([$idQuarto]);
    $quarto = $stmt->fetch();

    if (!$quarto || $quantidade > $quarto['capacidade']) {
        die("Quarto inválido ou capacidade excedida.");
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reserva r
        JOIN reserva_quarto rq ON r.id_reserva = rq.id_reserva
        WHERE rq.id_quarto = ?
        AND r.status IN ('pendente', 'confirmada')
        AND r.checkin_previsto < ?
        AND r.checkout_previsto > ?
    ");
    $stmt->execute([$idQuarto, $checkout, $checkin]);

    if ($stmt->fetchColumn() > 0) {
        die("Quarto indisponível para esse período.");
    }

    $valorTotal = $quarto['preco_diaria'] * $dias;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO reserva (
                id_hospede,
                data_reserva,
                checkin_previsto,
                checkout_previsto,
                quantidade_hospedes,
                status,
                valor_total
            )
            VALUES (?, CURRENT_DATE, ?, ?, ?, 'pendente', ?)
            RETURNING id_reserva
        ");

        $stmt->execute([
            $idHospede,
            $checkin,
            $checkout,
            $quantidade,
            $valorTotal
        ]);

        $idReserva = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO reserva_quarto (
                id_reserva,
                id_quarto,
                valor_diaria,
                quantidade_diarias,
                valor_total
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $idReserva,
            $idQuarto,
            $quarto['preco_diaria'],
            $dias,
            $valorTotal
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO historico_reserva (
                id_reserva,
                status_anterior,
                status_novo,
                observacao
            )
            VALUES (?, NULL, 'pendente', 'Reserva criada')
        ");

        $stmt->execute([$idReserva]);

        $pdo->commit();

        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao criar reserva: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Hotéis</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="sidebar">
    <h2>Armas & Rosas Hotel</h2>
    <a href="#dashboard">Dashboard</a>
    <a href="#reservas">Reservas</a>
    <a href="#quartos">Quartos</a>
    <a href="#hospedes">Hóspedes</a>
    <a href="#nova-reserva">Nova Reserva</a>
</div>

<main>
    <section id="dashboard">
        <h1>Dashboard</h1>

        <div class="cards">
            <div class="card">
                <h3>Hotéis</h3>
                <p><?= $totalHoteis ?></p>
            </div>

            <div class="card">
                <h3>Quartos</h3>
                <p><?= $totalQuartos ?></p>
            </div>

            <div class="card">
                <h3>Hóspedes</h3>
                <p><?= $totalHospedes ?></p>
            </div>

            <div class="card">
                <h3>Reservas</h3>
                <p><?= $totalReservas ?></p>
            </div>
        </div>
    </section>

    <section id="nova-reserva">
        <h2>Nova Reserva</h2>

        <form method="POST">

            <label>Hóspede</label>
            <select name="id_hospede" required>
                <option value="">Selecione</option>

                <?php foreach ($hospedes as $hospede): ?>
                    <option value="<?= $hospede['id_hospede'] ?>">
                        <?= htmlspecialchars($hospede['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Quarto</label>
            <select name="id_quarto" required>
                <option value="">Selecione</option>

                <?php foreach ($quartos as $quarto): ?>
                    <option value="<?= $quarto['id_quarto'] ?>">
                        Quarto <?= htmlspecialchars($quarto['numero']) ?>
                        - <?= htmlspecialchars($quarto['tipo_quarto']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Check-in</label>
            <input type="date" name="checkin" required>

            <label>Check-out</label>
            <input type="date" name="checkout" required>

            <label>Quantidade de hóspedes</label>
            <input type="number" name="quantidade_hospedes" min="1" required>

            <button type="submit">Cadastrar Reserva</button>

        </form>
    </section>

    <section id="reservas">
        <h2>Reservas</h2>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Hóspede</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th>Valor</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($reservas as $reserva): ?>
                    <tr>
                        <td><?= $reserva['id_reserva'] ?></td>
                        <td><?= htmlspecialchars($reserva['hospede']) ?></td>
                        <td><?= $reserva['checkin_previsto'] ?></td>
                        <td><?= $reserva['checkout_previsto'] ?></td>
                        <td><?= htmlspecialchars($reserva['status']) ?></td>
                        <td>R$ <?= number_format($reserva['valor_total'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section id="quartos">
        <h2>Quartos</h2>

        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Tipo</th>
                    <th>Andar</th>
                    <th>Status</th>
                    <th>Diária</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($quartos as $quarto): ?>
                    <tr>
                        <td><?= htmlspecialchars($quarto['numero']) ?></td>
                        <td><?= htmlspecialchars($quarto['tipo_quarto']) ?></td>
                        <td><?= htmlspecialchars($quarto['andar']) ?></td>
                        <td><?= htmlspecialchars($quarto['status']) ?></td>
                        <td>R$ <?= number_format($quarto['preco_diaria'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section id="hospedes">
        <h2>Hóspedes</h2>

        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>E-mail</th>
                    <th>Telefone</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($hospedes as $hospede): ?>
                    <tr>
                        <td><?= htmlspecialchars($hospede['nome']) ?></td>
                        <td><?= htmlspecialchars($hospede['cpf']) ?></td>
                        <td><?= htmlspecialchars($hospede['email']) ?></td>
                        <td><?= htmlspecialchars($hospede['telefone']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

</main>

</body>
</html>
