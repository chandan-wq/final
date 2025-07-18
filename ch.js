async function handleSubmit() {
  // ... existing validation code ...

  try {
    showLoading();
    
    const response = await fetch('http://your-backend-url/api/solve/text', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        question: state.currentQuestion,
        subject: state.selectedSubject,
        grade: state.selectedClass
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      displaySolution(data);
    } else {
      throw new Error(data.error || "Failed to get solution");
    }
    
  } catch (error) {
    console.error("Error:", error);
    showToast(error.message, 'error');
  }
}