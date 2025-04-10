import React, { Component } from 'react';

class ReactComponent extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      message: "Hello from React!",
      inputValue: "",
      returnedText: ""
    };
  }

  // Function to handle the text input change
  handleInputChange = (event) => {
    this.setState({ inputValue: event.target.value });
  };

  // Function to handle the text return button click
  handleReturnText = () => {
    this.setState({ returnedText: this.state.inputValue });
  };

  render() {
    return (
      <div className="max-w-md mx-auto bg-white shadow-lg rounded-lg p-6 mt-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-2">React Component</h2>
        <p className="text-gray-600 mb-4">{this.state.message}</p>

        <button
          onClick={() => this.setState({ message: "React says Hi!" })}
          className="mt-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring focus:ring-green-300"
        >
          Click Me to Change Message
        </button>

        <div className="mt-6">
          <h3 className="text-md font-semibold text-gray-800 mb-2">Text Input and Return</h3>
          <input
            type="text"
            value={this.state.inputValue}
            onChange={this.handleInputChange}
            className="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4 w-full"
            placeholder="Type something..."
          />
          <button
            onClick={this.handleReturnText}
            className="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring focus:ring-blue-300"
          >
            Return Text
          </button>
          {this.state.returnedText && (
            <p className="mt-4 text-gray-800 font-semibold">
              You typed: <span className="text-blue-600">{this.state.returnedText}</span>
            </p>
          )}
        </div>
      </div>
    );
  }
}

export default ReactComponent;
