<?php
// api/solve.php

require_once 'config.php';

header('Content-Type: application/json');

// Get the POST data from the JavaScript fetch call
$input = json_decode(file_get_contents('php://input'), true);

$sessionId = $input['sessionId'] ?? '';
$subject = $input['subject'] ?? 'General';
$class = $input['class'] ?? 'N/A';
$method = $input['method'] ?? 'Text';
$question = $input['question'] ?? '';

if (empty($sessionId) || empty($question)) {
    echo json_encode(['success' => false, 'error' => 'Missing session ID or question.']);
    exit;
}

// This function calls the Google Generative AI API
function callGenerativeAI($prompt, $apiKey) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true
        ],
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return ['success' => false, 'error' => 'API call failed.'];
    }

    $response = json_decode($result, true);
    
    // Check for API errors
    if (isset($response['error'])) {
         return ['success' => false, 'error' => 'API Error: ' . $response['error']['message']];
    }
    
    // Extract the content
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => true, 'text' => $response['candidates'][0]['content']['parts'][0]['text']];
    }
    
    return ['success' => false, 'error' => 'Could not parse AI response.', 'raw_response' => $response];
}

// --- Main Logic ---
$conn = getDbConnection();

// 1. Get or create the session
$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_id = ?");
$stmt->bind_param("s", $sessionId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $sessionRow = $result->fetch_assoc();
    $sessionIdFk = $sessionRow['id'];
} else {
    $stmt = $conn->prepare("INSERT INTO sessions (session_id) VALUES (?)");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $sessionIdFk = $stmt->insert_id;
}
$stmt->close();

// 2. Save the question
$stmt = $conn->prepare("INSERT INTO questions (session_id_fk, subject, class, method, question_text) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $sessionIdFk, $subject, $class, $method, $question);
$stmt->execute();
$questionIdFk = $stmt->insert_id;
$stmt->close();

// 3. Create a detailed prompt for the AI
$prompt = "You are 'Learnify Pro', a helpful AI study companion. A Class {$class} student is asking a question about {$subject}. Please provide a comprehensive answer. The user's question is: '{$question}'.
Structure your response in three distinct sections using these exact headers:
###SOLUTION###
(Provide a clear, step-by-step solution here. Use markdown for formatting, like lists, bold text, and code blocks for equations.)
###EXPLANATION###
(Explain the underlying concepts, the 'why' behind the solution. Break down complex ideas.)
###RESOURCES###
(Suggest 1-2 helpful, generic resources like 'a Khan Academy video on linear equations' or 'the Wikipedia page for the French Revolution'. Do not use real URLs.)";

// 4. Call the AI
$aiResponse = callGenerativeAI($prompt, GOOGLE_AI_API_KEY);

if (!$aiResponse['success']) {
    echo json_encode($aiResponse);
    exit;
}

// 5. Parse the AI response
$responseText = $aiResponse['text'];
$solution = 'Could not generate a solution.';
$explanation = 'Could not generate an explanation.';
$resources = 'No resources available.';

// Split the response text by the headers
$parts = preg_split('/###(SOLUTION|EXPLANATION|RESOURCES)###/', $responseText, -1, PREG_SPLIT_NO_EMPTY);

if (count($parts) >= 3) {
    $solution = trim($parts[0]);
    $explanation = trim($parts[1]);
    $resources = trim($parts[2]);
}

// We'll use a simple markdown parser for demonstration. A more robust library would be better.
require_once 'Parsedown.php'; // You will need to download this library
$Parsedown = new Parsedown();

$solutionHtml = $Parsedown->text($solution);
$explanationHtml = $Parsedown->text($explanation);
$resourcesHtml = $Parsedown->text($resources);


// 6. Save the solution
$stmt = $conn->prepare("INSERT INTO solutions (question_id_fk, solution_html, explanation_html, resources_html) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $questionIdFk, $solutionHtml, $explanationHtml, $resourcesHtml);
$stmt->execute();
$stmt->close();
$conn->close();

// 7. Send the response back to the JavaScript
echo json_encode([
    'success' => true,
    'solution' => $solutionHtml,
    'explanation' => $explanationHtml,
    'resources' => $resourcesHtml
]);

?>