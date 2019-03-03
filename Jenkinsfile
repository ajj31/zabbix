pipeline {
    agent any
    stages {
        stage('Build') { 
            steps {
                sh './configure' 
                sh 'make' 
                sh 'sudo make install' 
            }
        }
    }
}
