/*
function showsum()
{
    let x=5;
    let y=25;
    document.getElementById("result").innerHTML=x+y;
}

<button type="button" onclick="showsum()">cllick me</button>

  str=5;
            str++;
            printthook();
            function printthook(){
                document.write('thoook');
            }
             document.write(' shaan dabhi')
*/
const loginForm = document.getElementById("loginForm");

if (loginForm) {
    loginForm.addEventListener("submit", function(event) {
        event.preventDefault();

        let username = document.getElementById("username").value;
        let password = document.getElementById("password").value;

        if (username.length < 4) {
            alert("Username must be at least 4 characters!");
            return;
        }

        if (password.length < 6) {
            alert("Password must be at least 6 characters!");
            return;
        }

        alert("Login successful!");
    });
}

const adminForm = document.getElementById("adminform");

if (adminForm) {
    adminForm.addEventListener("submit", function(event) {
        event.preventDefault();

        let username = document.getElementById("adminusername").value;
        let password = document.getElementById("adminpassword").value;

        if (username.length < 4) {
            alert("Admin username must be at least 4 characters!");
            return;
        }

        if (password.length < 6) {
            alert("Admin password must be at least 6 characters!");
            return;
        }

        alert("Login successful!");
    });
}