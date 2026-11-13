<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexao.php';

$method = $_SERVER['REQUEST_METHOD'];

// SE FOR GET: O navegador está pedindo a lista de livros e leitores
if ($method === 'GET') {
    // 1. Busca os livros e seus empréstimos ativos
    $sqlLivros = "SELECT 
                l.id_livro AS id,
                l.titulo_livro AS titulo,
                l.autor_livro AS autor,
                e.data_emprestimo AS desde,
                e.data_prevista_devolucao AS ate,
                lei.nome_leitor AS leitor
            FROM tb_livros l
            LEFT JOIN tb_itens_emprestimo ie ON l.id_livro = ie.fk_livro AND ie.data_devolucao IS NULL
            LEFT JOIN tb_emprestimos e ON ie.fk_emprestimo = e.id_emprestimo
            LEFT JOIN tb_leitores lei ON e.fk_leitor = lei.id_leitor";
    
    $resLivros = $mysqli->query($sqlLivros);
    $livros = [];
    if ($resLivros) {
        while ($linha = $resLivros->fetch_assoc()) {
            $emprestimo = null;
            if ($linha['leitor'] !== null) {
                $emprestimo = [
                    'leitor' => $linha['leitor'],
                    'desde'  => $linha['desde'],
                    'ate'    => $linha['ate']
                ];
            }
            $livros[] = [
                'id'         => (int)$linha['id'],
                'titulo'     => $linha['titulo'],
                'autor'      => $linha['autor'],
                'emprestimo' => $emprestimo
            ];
        }
    }

    // 2. Busca os leitores cadastrados
    $leitores = [];
    $resLeitores = $mysqli->query("SELECT nome_leitor FROM tb_leitores ORDER BY nome_leitor");
    if ($resLeitores) {
        while ($r = $resLeitores->fetch_assoc()) {
            $leitores[] = $r['nome_leitor'];
        }
    }

    echo json_encode(["livros" => $livros, "leitores" => $leitores]);
    exit;
}

// SE FOR POST: O navegador está mandando gravar ou alterar algo
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $acao = $input['acao'] ?? '';

    if ($acao === 'emprestar') {
        $leitor = $input['leitor'];
        $id_livro = (int)$input['id_livro'];
        $semanas = (int)$input['semanas'];
        
        $hoje = date('Y-m-d');
        $ate = date('Y-m-d', strtotime("+$semanas weeks"));

        // Descobre o ID do leitor ou cadastra um novo na hora
        $id_leitor = null;
        $stmt = $mysqli->prepare("SELECT id_leitor FROM tb_leitores WHERE nome_leitor = ?");
        $stmt->bind_param("s", $leitor);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $id_leitor = $row['id_leitor'];
        } else {
            $stmtIns = $mysqli->prepare("INSERT INTO tb_leitores (nome_leitor) VALUES (?)");
            $stmtIns->bind_param("s", $leitor);
            $stmtIns->execute();
            $id_leitor = $stmtIns->insert_id;
        }

        // Cria o registro principal de empréstimo
        $stmtEmp = $mysqli->prepare("INSERT INTO tb_emprestimos (fk_leitor, data_emprestimo, data_prevista_devolucao) VALUES (?, ?, ?)");
        $stmtEmp->bind_param("iss", $id_leitor, $hoje, $ate);
        $stmtEmp->execute();
        $id_emprestimo = $stmtEmp->insert_id;

        // Associa o livro ao empréstimo
        $stmtItem = $mysqli->prepare("INSERT INTO tb_itens_emprestimo (fk_emprestimo, fk_livro) VALUES (?, ?)");
        $stmtItem->bind_param("ii", $id_emprestimo, $id_livro);
        $stmtItem->execute();

        echo json_encode(["sucesso" => true]);
        exit;
    }

    if ($acao === 'devolver') {
        $id_livro = (int)$input['id_livro'];
        $hoje = date('Y-m-d');
        
        // Marca a data de devolução no item
        $stmt = $mysqli->prepare("UPDATE tb_itens_emprestimo SET data_devolucao = ? WHERE fk_livro = ? AND data_devolucao IS NULL");
        $stmt->bind_param("si", $hoje, $id_livro);
        $stmt->execute();

        echo json_encode(["sucesso" => true]);
        exit;
    }

    if ($acao === 'desfazer_devolucao') {
        $id_livro = (int)$input['id_livro'];
        // Remove a data de devolução do último registro desse livro
        $stmt = $mysqli->prepare("UPDATE tb_itens_emprestimo SET data_devolucao = NULL WHERE fk_livro = ? ORDER BY id_item_emprestimo DESC LIMIT 1");
        $stmt->bind_param("i", $id_livro);
        $stmt->execute();

        echo json_encode(["sucesso" => true]);
        exit;
    }
}
?>