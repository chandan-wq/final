// 1. For text questions
async function submitQuestion() {
  const response = await fetch('http://localhost:3000/api/ai-answer', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      question: "Explain photosynthesis",
      subject: "Science",
      grade: 7
    })
  });
  const data = await response.json();
  console.log(data.solution);
}

// 2. For math images
async function uploadMathImage(file) {
  const formData = new FormData();
  formData.append('image', file);
  
  const response = await fetch('http://localhost:3000/api/process-math-image', {
    method: 'POST',
    body: formData
  });
  const data = await response.json();
  console.log(data.text); // LaTeX output
}

// 3. For voice recordings
async function transcribeAudio(audioBlob) {
  const formData = new FormData();
  formData.append('audio', audioBlob, 'recording.webm');
  
  const response = await fetch('http://localhost:3000/api/transcribe-audio', {
    method: 'POST',
    body: formData
  });
  const data = await response.json();
  console.log(data.transcript);
}